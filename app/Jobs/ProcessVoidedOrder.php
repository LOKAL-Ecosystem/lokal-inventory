<?php

namespace App\Jobs;

use App\Models\Outlet;
use App\Services\StockDeductionService;

class ProcessVoidedOrder extends ProcessesInbox
{
    protected function process(Outlet $outlet, array $data): void
    {
        app(StockDeductionService::class)->processVoided($data, $outlet);
    }
}
