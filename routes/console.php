<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pos:debug-inv14', function () {
    $pi = \App\Models\PurchaseInvoice::with('items.item')->find(14);
    if (! $pi) {
        $this->error('PI 14 not found');
        return;
    }
    $this->info("Invoice: {$pi->invoice_number}");
    foreach ($pi->items as $it) {
        $m = $it->item;
        $code = $m?->ean_upc_code ?: $m?->item_code ?: (string) $m?->id;
        $this->line("Item ID={$it->item_id} | Name={$m?->name} | Code={$m?->item_code} | EAN={$m?->ean_upc_code} | Barcode={$code} | Qty={$it->qty} | Price={$it->sell_price} | MRP={$it->mrp}");
    }
});

Schedule::command('pos:heartbeat')->hourly();
Schedule::command('pos:backup')->dailyAt('02:00');
Schedule::command('db:backup --clean')->dailyAt('01:00');
