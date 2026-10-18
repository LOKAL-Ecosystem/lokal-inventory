<?php

namespace App\Jobs;

use App\Models\Outlet;
use App\Services\StockDeductionService;

class ProcessConfirmedOrder extends ProcessesInbox
{
    protected function process(Outlet $outlet, array $data): void
    {
        app(StockDeductionService::class)->processConfirmed($data, $outlet);
    }
}
