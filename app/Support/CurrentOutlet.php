<?php

namespace App\Support;

use App\Models\Outlet;

/**
 * Menentukan outlet aktif. Job & webhook tidak punya sesi pengguna, jadi outlet
 * diset eksplisit lewat run()/set(); request web memakai outlet milik user.
 */
class CurrentOutlet
{
    protected ?int $explicitId = null;

    public function set(Outlet|int|null $outlet): void
    {
        $this->explicitId = $outlet instanceof Outlet ? $outlet->id : $outlet;
    }

    public function id(): ?int
    {
        return $this->explicitId ?? auth()->user()?->outlet_id;
    }

    public function outlet(): ?Outlet
    {
        $id = $this->id();

        return $id ? Outlet::find($id) : null;
    }

    /**
     * Jalankan callback dengan outlet tertentu, lalu kembalikan konteks sebelumnya.
     */
    public function run(Outlet|int $outlet, callable $callback): mixed
    {
        $previous = $this->explicitId;
        $this->set($outlet);

        try {
            return $callback();
        } finally {
            $this->explicitId = $previous;
        }
    }
}
