<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Penautan otomatis dari Lokal POS (kontrak v2.3): outlet dan user cermin dibuat atau diperbarui
 * dari data yang dikirim POS. Idempoten pada pos_outlet_id dan (outlet, pos_user_id).
 */
class OutletLinkService
{
    /**
     * @param  array{pos_outlet_id: int, name: string, pos_base_url: string, api_token: string, webhook_secret: string, staff?: array<int, array<string, mixed>>}  $data
     */
    public function link(array $data): Outlet
    {
        return DB::transaction(function () use ($data) {
            $outlet = Outlet::lockForUpdate()->firstOrNew(['pos_outlet_id' => (int) $data['pos_outlet_id']]);
            $outlet->fill([
                'name' => $data['name'],
                'pos_base_url' => $data['pos_base_url'],
                'pos_api_key' => $data['api_token'],
                'webhook_secret' => $data['webhook_secret'],
            ]);
            $outlet->linked_at = now();
            $outlet->save();

            foreach ($data['staff'] ?? [] as $member) {
                $this->syncStaff($outlet, $member);
            }

            return $outlet;
        });
    }

    /**
     * Buat/perbarui user cermin dari satu staf POS. User lama di outlet yang sama dengan email sama
     * dan belum tertaut (mis. akun demo) diambil alih, supaya datanya tetap tersambung.
     *
     * @param  array{pos_user_id: int|string, name: string, email: string, role?: string|null, is_active?: bool|int|null}  $member
     */
    public function syncStaff(Outlet $outlet, array $member): User
    {
        $posUserId = (int) $member['pos_user_id'];

        $user = User::where('outlet_id', $outlet->id)->where('pos_user_id', $posUserId)->first()
            ?? User::where('outlet_id', $outlet->id)->whereNull('pos_user_id')->where('email', $member['email'])->first()
            ?? new User(['outlet_id' => $outlet->id]);

        $isActive = (bool) ($member['is_active'] ?? true);
        $deactivated = $user->exists && $user->is_active && ! $isActive;

        $user->forceFill([
            'pos_user_id' => $posUserId,
            'name' => $member['name'],
            'email' => $this->emailFor($user, $outlet, $posUserId, (string) $member['email']),
            'role' => UserRole::tryFrom((string) ($member['role'] ?? '')) ?? UserRole::Cashier,
            'is_active' => $isActive,
        ]);

        if ($deactivated) {
            // Sesi "ingat saya" ikut gugur; sesi aktif diputus oleh middleware EnsureUserIsActive.
            $user->setRememberToken(null);
        }

        $user->save();

        return $user;
    }

    /**
     * Email unik di seluruh aplikasi. Bila email itu sudah dipakai user lain (outlet lain), user cermin
     * memakai alamat pengganti yang tidak bisa menerima surat; login tetap lewat POS.
     */
    protected function emailFor(User $user, Outlet $outlet, int $posUserId, string $email): string
    {
        $taken = User::where('email', $email)->when($user->exists, fn ($q) => $q->whereKeyNot($user->id))->exists();

        return $taken ? "pos{$outlet->pos_outlet_id}-{$posUserId}@users.lokal.invalid" : $email;
    }
}
