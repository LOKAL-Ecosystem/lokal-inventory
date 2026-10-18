<?php

namespace App\Models\Concerns;

use App\Models\Outlet;
use App\Support\CurrentOutlet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Isolasi data per outlet. Tanpa outlet aktif, query tidak mengembalikan apa pun (fail closed).
 */
trait BelongsToOutlet
{
    public static function bootBelongsToOutlet(): void
    {
        static::addGlobalScope('outlet', function (Builder $query) {
            $outletId = app(CurrentOutlet::class)->id();
            $table = $query->getModel()->getTable();

            if ($outletId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where("{$table}.outlet_id", $outletId);
        });

        static::creating(function ($model) {
            if ($model->outlet_id !== null) {
                return;
            }

            $outletId = app(CurrentOutlet::class)->id();

            if ($outletId === null) {
                throw new LogicException('Tidak ada outlet aktif untuk menyimpan ' . class_basename($model) . '.');
            }

            $model->outlet_id = $outletId;
        });
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
