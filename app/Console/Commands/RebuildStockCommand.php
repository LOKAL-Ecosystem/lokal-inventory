<?php

namespace App\Console\Commands;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Services\MenuAvailabilityService;
use App\Services\StockService;
use App\Support\CurrentOutlet;
use Illuminate\Console\Command;

class RebuildStockCommand extends Command
{
    protected $signature = 'inventory:rebuild-stock {--outlet= : ID outlet (default: semua)} {--dry-run : Hanya tampilkan selisih}';

    protected $description = 'Hitung ulang ingredients.current_stock dari ledger stock_movements';

    public function handle(StockService $stock, CurrentOutlet $current, MenuAvailabilityService $availability): int
    {
        $outlets = Outlet::query()
            ->when($this->option('outlet'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($outlets as $outlet) {
            $current->run($outlet, function () use ($outlet, $stock, $availability) {
                $fixed = 0;

                foreach (Ingredient::all() as $ingredient) {
                    $before = (float) $ingredient->current_stock;

                    if ($this->option('dry-run')) {
                        $after = (float) $ingredient->movements()->sum('qty');
                    } else {
                        $after = $stock->rebuild($ingredient);
                    }

                    if (abs($before - $after) > 0.0005) {
                        $fixed++;
                        $this->line("[{$outlet->name}] {$ingredient->name}: {$before} -> {$after}");
                    }
                }

                if (! $this->option('dry-run')) {
                    $availability->refreshAll();
                }

                $this->info("{$outlet->name}: {$fixed} bahan " . ($this->option('dry-run') ? 'berbeda' : 'dikoreksi') . '.');
            });
        }

        return self::SUCCESS;
    }
}
