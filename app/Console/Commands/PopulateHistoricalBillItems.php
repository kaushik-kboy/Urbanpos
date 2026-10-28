<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\SalesBill;
use App\Models\PurchaseInvoice;
use App\Models\Item;
use Carbon\Carbon;

class PopulateHistoricalBillItems extends Command
{
    protected $signature = 'bills:populate-historical-items';
    protected $description = 'Safely populate exact items for historical Sales Bill CO-225-37430 and Purchase Invoice GRN-4377 without touching physical stock';

    public function handle()
    {
        $this->info("========================================================");
        $this->info("   SAFE HISTORICAL ITEMS POPULATION (ZERO STOCK IMPACT) ");
        $this->info("========================================================");

        DB::beginTransaction();

        try {
            // -------------------------------------------------------------
            // 1. SALES BILL: CO-225-37430
            // -------------------------------------------------------------
            $salesBill = SalesBill::where('bill_number', 'CO-225-37430')
                ->orWhere('bill_number', 'like', '%37430')
                ->orWhere('id', 19071)
                ->first();

            if (!$salesBill) {
                $this->warn("[!] Sales Bill CO-225-37430 not found in database. Skipping sales bill.");
            } else {
                $this->info("\n[1] Processing Sales Bill: {$salesBill->bill_number} (ID: {$salesBill->id})");

                $itemDrools = Item::where('item_code', '11361')->firstOrFail();
                $itemChain = Item::where('item_code', '10719')->firstOrFail();

                // Snapshot stocks before
                $stockDroolsBefore = (float)DB::table('item_stocks')->where('item_id', $itemDrools->id)->sum('quantity');
                $stockChainBefore = (float)DB::table('item_stocks')->where('item_id', $itemChain->id)->sum('quantity');
                $stockLedgerBefore = DB::table('stock_ledger')->count();

                $this->line("    Stock Before -> Drools Veg 20KG (ID: {$itemDrools->id}): {$stockDroolsBefore}");
                $this->line("    Stock Before -> CH-050 Chain no.6 (ID: {$itemChain->id}): {$stockChainBefore}");

                // Clear any existing dummy/placeholder items for this bill
                DB::table('sales_bill_items')->where('sales_bill_id', $salesBill->id)->delete();

                $billItems = [
                    [
                        'sales_bill_id' => $salesBill->id,
                        'item_id' => $itemDrools->id,
                        'batch_no' => '30-05-2028',
                        'exp_date' => '2028-05-30',
                        'qty' => 3.000,
                        'sell_price' => 4500.00,
                        'cost_at_sale' => 0.00,
                        'mrp' => 4500.00,
                        'disc_percent' => 0.00,
                        'disc_amount' => 5700.00,
                        'gst_percent' => 18.00,
                        'gst_tax_amount' => 1189.83,
                        'cgst_amount' => 594.92,
                        'sgst_amount' => 594.91,
                        'igst_amount' => 0.00,
                        'net_amount' => 7800.00,
                        'created_at' => Carbon::parse('2026-08-20 15:21:00'),
                        'updated_at' => Carbon::now(),
                    ],
                    [
                        'sales_bill_id' => $salesBill->id,
                        'item_id' => $itemDrools->id,
                        'batch_no' => '30-05-2028',
                        'exp_date' => '2028-05-30',
                        'qty' => 10.000,
                        'sell_price' => 4500.00,
                        'cost_at_sale' => 0.00,
                        'mrp' => 4500.00,
                        'disc_percent' => 0.00,
                        'disc_amount' => 19000.00,
                        'gst_percent' => 18.00,
                        'gst_tax_amount' => 3966.10,
                        'cgst_amount' => 1983.05,
                        'sgst_amount' => 1983.05,
                        'igst_amount' => 0.00,
                        'net_amount' => 26000.00,
                        'created_at' => Carbon::parse('2026-08-20 15:21:00'),
                        'updated_at' => Carbon::now(),
                    ],
                    [
                        'sales_bill_id' => $salesBill->id,
                        'item_id' => $itemDrools->id,
                        'batch_no' => '15-03-2028',
                        'exp_date' => '2028-03-15',
                        'qty' => 2.000,
                        'sell_price' => 4500.00,
                        'cost_at_sale' => 0.00,
                        'mrp' => 4500.00,
                        'disc_percent' => 0.00,
                        'disc_amount' => 3800.00,
                        'gst_percent' => 18.00,
                        'gst_tax_amount' => 793.22,
                        'cgst_amount' => 396.61,
                        'sgst_amount' => 396.61,
                        'igst_amount' => 0.00,
                        'net_amount' => 5200.00,
                        'created_at' => Carbon::parse('2026-08-20 15:21:00'),
                        'updated_at' => Carbon::now(),
                    ],
                    [
                        'sales_bill_id' => $salesBill->id,
                        'item_id' => $itemChain->id,
                        'batch_no' => '30-10-2029',
                        'exp_date' => '2029-10-30',
                        'qty' => 10.000,
                        'sell_price' => 1200.00,
                        'cost_at_sale' => 0.00,
                        'mrp' => 1200.00,
                        'disc_percent' => 99.99,
                        'disc_amount' => 11998.80,
                        'gst_percent' => 18.00,
                        'gst_tax_amount' => 0.18,
                        'cgst_amount' => 0.09,
                        'sgst_amount' => 0.09,
                        'igst_amount' => 0.00,
                        'net_amount' => 1.20,
                        'created_at' => Carbon::parse('2026-08-20 15:21:00'),
                        'updated_at' => Carbon::now(),
                    ],
                ];

                DB::table('sales_bill_items')->insert($billItems);
                $this->info("    [+] Inserted " . count($billItems) . " lines into sales_bill_items.");

                // Update bill header totals to match exact TruePOS figures
                DB::table('sales_bills')->where('id', $salesBill->id)->update([
                    'total_qty' => 25.000,
                    'item_disc_amount' => 40498.80,
                    'disc_amount' => 0.00,
                    'tax_amount' => 5949.33,
                    'round_off' => -0.20,
                    'total' => 39001.00,
                    'remarks' => 'K',
                    'updated_at' => Carbon::now(),
                ]);

                // Verify stock after
                $stockDroolsAfter = (float)DB::table('item_stocks')->where('item_id', $itemDrools->id)->sum('quantity');
                $stockChainAfter = (float)DB::table('item_stocks')->where('item_id', $itemChain->id)->sum('quantity');
                $this->line("    Stock After  -> Drools Veg 20KG: {$stockDroolsAfter} (Diff: 0.000)");
                $this->line("    Stock After  -> CH-050 Chain no.6: {$stockChainAfter} (Diff: 0.000)");

                if ($stockDroolsAfter !== $stockDroolsBefore || $stockChainAfter !== $stockChainBefore) {
                    throw new \Exception("FATAL: Stock changed unexpectedly for sales bill items!");
                }
            }

            // -------------------------------------------------------------
            // 2. PURCHASE INVOICE: GRN-4377 / 2367
            // -------------------------------------------------------------
            $purchaseInvoice = PurchaseInvoice::where('invoice_number', 'GRN-4377')
                ->orWhere('supplier_inv_no', 'SIPP/1515/26-27')
                ->orWhere('id', 253)
                ->first();

            if (!$purchaseInvoice) {
                $this->warn("[!] Purchase Invoice GRN-4377 not found in database. Skipping purchase invoice.");
            } else {
                $this->info("\n[2] Processing Purchase Invoice: {$purchaseInvoice->invoice_number} (ID: {$purchaseInvoice->id})");

                $grnRaw = [
            [
                'sno' => 1,
                'code' => '7663',
                'desc' => 'JH 70G Strawberry',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 1323.0,
                'disc_p' => 2.0,
                'disc_amt' => 26.46,
                'gst_p' => 18.0,
                'gst_amt' => 233.38,
            ],
            [
                'sno' => 2,
                'code' => '7402',
                'desc' => 'JH 70G Banana',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 1323.0,
                'disc_p' => 2.0,
                'disc_amt' => 26.46,
                'gst_p' => 18.0,
                'gst_amt' => 233.38,
            ],
            [
                'sno' => 3,
                'code' => '7645',
                'desc' => 'JH 70G Carrot Stick',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 1323.0,
                'disc_p' => 2.0,
                'disc_amt' => 26.46,
                'gst_p' => 18.0,
                'gst_amt' => 233.38,
            ],
            [
                'sno' => 4,
                'code' => '7665',
                'desc' => 'JH 70G Spinach',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 1323.0,
                'disc_p' => 2.0,
                'disc_amt' => 26.46,
                'gst_p' => 18.0,
                'gst_amt' => 233.38,
            ],
            [
                'sno' => 5,
                'code' => '7503',
                'desc' => 'JH 70G Blueberry',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 1323.0,
                'disc_p' => 2.0,
                'disc_amt' => 26.46,
                'gst_p' => 18.0,
                'gst_amt' => 233.38,
            ],
            [
                'sno' => 6,
                'code' => '7666',
                'desc' => 'JH 70G Milky',
                'exp' => '30-11-2027',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 2646.0,
                'disc_p' => 2.0,
                'disc_amt' => 52.92,
                'gst_p' => 18.0,
                'gst_amt' => 466.75,
            ],
            [
                'sno' => 7,
                'code' => '9736',
                'desc' => 'JH 100 G Milky Stix',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 144.55,
                'sell' => 295.0,
                'mrp' => 295.0,
                'gross' => 1734.6,
                'disc_p' => 2.0,
                'disc_amt' => 34.69,
                'gst_p' => 18.0,
                'gst_amt' => 305.98,
            ],
            [
                'sno' => 8,
                'code' => '7642',
                'desc' => 'JH 100G Mini Burger',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 144.55,
                'sell' => 295.0,
                'mrp' => 295.0,
                'gross' => 1734.6,
                'disc_p' => 2.0,
                'disc_amt' => 34.69,
                'gst_p' => 18.0,
                'gst_amt' => 305.98,
            ],
            [
                'sno' => 9,
                'code' => '7682',
                'desc' => 'JH 100G Banana',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 144.55,
                'sell' => 295.0,
                'mrp' => 295.0,
                'gross' => 1734.6,
                'disc_p' => 2.0,
                'disc_amt' => 34.69,
                'gst_p' => 18.0,
                'gst_amt' => 305.98,
            ],
            [
                'sno' => 10,
                'code' => '7680',
                'desc' => 'JH 100G Strawberry',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 144.55,
                'sell' => 295.0,
                'mrp' => 295.0,
                'gross' => 1734.6,
                'disc_p' => 2.0,
                'disc_amt' => 34.69,
                'gst_p' => 18.0,
                'gst_amt' => 305.98,
            ],
            [
                'sno' => 11,
                'code' => '7762',
                'desc' => 'JH 70g Morning Time Stick',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 1323.0,
                'disc_p' => 2.0,
                'disc_amt' => 26.46,
                'gst_p' => 18.0,
                'gst_amt' => 233.38,
            ],
            [
                'sno' => 12,
                'code' => '6053',
                'desc' => 'JH 70g Bed Time',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 1323.0,
                'disc_p' => 2.0,
                'disc_amt' => 26.46,
                'gst_p' => 18.0,
                'gst_amt' => 233.38,
            ],
            [
                'sno' => 13,
                'code' => '7641',
                'desc' => 'JH 100G Carrot Stix 100G',
                'exp' => '30-11-2027',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 144.55,
                'sell' => 295.0,
                'mrp' => 295.0,
                'gross' => 3469.2,
                'disc_p' => 2.0,
                'disc_amt' => 69.38,
                'gst_p' => 18.0,
                'gst_amt' => 611.97,
            ],
            [
                'sno' => 14,
                'code' => '7681',
                'desc' => 'JH 100G Blueberry',
                'exp' => '30-11-2027',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 144.55,
                'sell' => 295.0,
                'mrp' => 295.0,
                'gross' => 3469.2,
                'disc_p' => 2.0,
                'disc_amt' => 69.38,
                'gst_p' => 18.0,
                'gst_amt' => 611.97,
            ],
            [
                'sno' => 15,
                'code' => '7653',
                'desc' => 'JH 100G Spinach Stix',
                'exp' => '30-11-2027',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 144.55,
                'sell' => 295.0,
                'mrp' => 295.0,
                'gross' => 3469.2,
                'disc_p' => 2.0,
                'disc_amt' => 69.38,
                'gst_p' => 18.0,
                'gst_amt' => 611.97,
            ],
            [
                'sno' => 16,
                'code' => '7394',
                'desc' => 'JH 100G Variety Stix',
                'exp' => '30-11-2027',
                'qty' => 48.0,
                'free' => 0.0,
                'cost' => 269.5,
                'sell' => 550.0,
                'mrp' => 550.0,
                'gross' => 12936.0,
                'disc_p' => 2.0,
                'disc_amt' => 258.72,
                'gst_p' => 18.0,
                'gst_amt' => 2281.91,
            ],
            [
                'sno' => 17,
                'code' => '7671',
                'desc' => 'JH Chicken Milky Stick 400G',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 526.75,
                'sell' => 1075.0,
                'mrp' => 1075.0,
                'gross' => 6321.0,
                'disc_p' => 2.0,
                'disc_amt' => 126.42,
                'gst_p' => 18.0,
                'gst_amt' => 1115.02,
            ],
            [
                'sno' => 18,
                'code' => '7675',
                'desc' => 'JH Chicken Spinach Stick 400G',
                'exp' => '30-11-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 526.75,
                'sell' => 1075.0,
                'mrp' => 1075.0,
                'gross' => 6321.0,
                'disc_p' => 2.0,
                'disc_amt' => 126.42,
                'gst_p' => 18.0,
                'gst_amt' => 1115.02,
            ],
            [
                'sno' => 19,
                'code' => '7676',
                'desc' => 'JH Chicken Strawberry Stick 400G',
                'exp' => '30-11-2027',
                'qty' => 8.0,
                'free' => 0.0,
                'cost' => 526.75,
                'sell' => 1075.0,
                'mrp' => 1075.0,
                'gross' => 4214.0,
                'disc_p' => 2.0,
                'disc_amt' => 84.28,
                'gst_p' => 18.0,
                'gst_amt' => 743.35,
            ],
            [
                'sno' => 20,
                'code' => '7674',
                'desc' => 'JH Carrot Stick 420G',
                'exp' => '30-12-2027',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 526.75,
                'sell' => 1075.0,
                'mrp' => 1075.0,
                'gross' => 12642.0,
                'disc_p' => 2.0,
                'disc_amt' => 252.84,
                'gst_p' => 18.0,
                'gst_amt' => 2230.05,
            ],
            [
                'sno' => 21,
                'code' => '7668',
                'desc' => 'JH Duo Stick Chicken Cheese Stick 50G',
                'exp' => '30-10-2027',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 2646.0,
                'disc_p' => 2.0,
                'disc_amt' => 52.92,
                'gst_p' => 18.0,
                'gst_amt' => 466.75,
            ],
            [
                'sno' => 22,
                'code' => '7667',
                'desc' => 'JH Duo Stick Milky Banana Stick 50G',
                'exp' => '30-12-2027',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 2646.0,
                'disc_p' => 2.0,
                'disc_amt' => 52.92,
                'gst_p' => 18.0,
                'gst_amt' => 466.75,
            ],
            [
                'sno' => 23,
                'code' => '7669',
                'desc' => 'JH Duo Stick Milky Strawberry Stick 50G',
                'exp' => '30-12-2027',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 2646.0,
                'disc_p' => 2.0,
                'disc_amt' => 52.92,
                'gst_p' => 18.0,
                'gst_amt' => 466.75,
            ],
            [
                'sno' => 24,
                'code' => '7672',
                'desc' => 'JH Duo Stick Spinach Cheese Stick 50G',
                'exp' => '30-10-2027',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 110.25,
                'sell' => 225.0,
                'mrp' => 225.0,
                'gross' => 2646.0,
                'disc_p' => 2.0,
                'disc_amt' => 52.92,
                'gst_p' => 18.0,
                'gst_amt' => 466.75,
            ],
            [
                'sno' => 25,
                'code' => '7658',
                'desc' => 'JH Hot Dog Bar Chicken and Veg 150G',
                'exp' => '30-11-2027',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 97.51,
                'sell' => 199.0,
                'mrp' => 199.0,
                'gross' => 2340.24,
                'disc_p' => 2.0,
                'disc_amt' => 46.8,
                'gst_p' => 18.0,
                'gst_amt' => 412.82,
            ],
            [
                'sno' => 26,
                'code' => '7540',
                'desc' => 'GN Calcium Milk Bone Small 30X1',
                'exp' => '30-12-2027',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 208.25,
                'sell' => 425.0,
                'mrp' => 425.0,
                'gross' => 2499.0,
                'disc_p' => 2.0,
                'disc_amt' => 49.98,
                'gst_p' => 18.0,
                'gst_amt' => 440.82,
            ],
            [
                'sno' => 27,
                'code' => '7547',
                'desc' => 'GN Calcium Milk Bone Small 90X1',
                'exp' => '30-04-2028',
                'qty' => 15.0,
                'free' => 0.0,
                'cost' => 575.75,
                'sell' => 1175.0,
                'mrp' => 1175.0,
                'gross' => 8636.25,
                'disc_p' => 2.0,
                'disc_amt' => 172.73,
                'gst_p' => 18.0,
                'gst_amt' => 1523.43,
            ],
            [
                'sno' => 28,
                'code' => '7525',
                'desc' => 'GN Calcium Milk Bone Large 12X1',
                'exp' => '30-03-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 208.25,
                'sell' => 425.0,
                'mrp' => 425.0,
                'gross' => 2499.0,
                'disc_p' => 2.0,
                'disc_amt' => 49.98,
                'gst_p' => 18.0,
                'gst_amt' => 440.82,
            ],
            [
                'sno' => 29,
                'code' => '7515',
                'desc' => 'GN Calcium Milk Sticks 270Gms',
                'exp' => '30-03-2028',
                'qty' => 48.0,
                'free' => 0.0,
                'cost' => 171.5,
                'sell' => 350.0,
                'mrp' => 350.0,
                'gross' => 8232.0,
                'disc_p' => 2.0,
                'disc_amt' => 164.64,
                'gst_p' => 18.0,
                'gst_amt' => 1452.12,
            ],
            [
                'sno' => 30,
                'code' => '7514',
                'desc' => 'GN Calcium Chicken sticks 270Gms',
                'exp' => '30-04-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 171.5,
                'sell' => 350.0,
                'mrp' => 350.0,
                'gross' => 2058.0,
                'disc_p' => 2.0,
                'disc_amt' => 41.16,
                'gst_p' => 18.0,
                'gst_amt' => 363.03,
            ],
            [
                'sno' => 31,
                'code' => '7716',
                'desc' => 'Dentalight Dental Bone 60PCS',
                'exp' => '30-05-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 428.75,
                'sell' => 875.0,
                'mrp' => 875.0,
                'gross' => 5145.0,
                'disc_p' => 2.0,
                'disc_amt' => 102.9,
                'gst_p' => 18.0,
                'gst_amt' => 907.58,
            ],
            [
                'sno' => 32,
                'code' => '7717',
                'desc' => 'Dentalight Dental Bone Small 12PCS',
                'exp' => '30-04-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 85.75,
                'sell' => 175.0,
                'mrp' => 175.0,
                'gross' => 1029.0,
                'disc_p' => 2.0,
                'disc_amt' => 20.58,
                'gst_p' => 18.0,
                'gst_amt' => 181.52,
            ],
            [
                'sno' => 33,
                'code' => '7532',
                'desc' => 'GN Oat Bone (s) 225G -25 pcs',
                'exp' => '30-06-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 306.25,
                'sell' => 625.0,
                'mrp' => 625.0,
                'gross' => 3675.0,
                'disc_p' => 2.0,
                'disc_amt' => 73.5,
                'gst_p' => 18.0,
                'gst_amt' => 648.27,
            ],
            [
                'sno' => 34,
                'code' => '7535',
                'desc' => 'GN Hearty Roll 5 Inch',
                'exp' => '30-03-2028',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 34.3,
                'sell' => 70.0,
                'mrp' => 70.0,
                'gross' => 823.2,
                'disc_p' => 2.0,
                'disc_amt' => 16.46,
                'gst_p' => 18.0,
                'gst_amt' => 145.21,
            ],
            [
                'sno' => 35,
                'code' => '7536',
                'desc' => 'GN Hearty Roll 9 Inch',
                'exp' => '30-03-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 58.8,
                'sell' => 120.0,
                'mrp' => 120.0,
                'gross' => 705.6,
                'disc_p' => 2.0,
                'disc_amt' => 14.11,
                'gst_p' => 18.0,
                'gst_amt' => 124.47,
            ],
            [
                'sno' => 36,
                'code' => '7530',
                'desc' => 'GN 5 Inch Braided Sticks 500G',
                'exp' => '30-03-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 487.55,
                'sell' => 995.0,
                'mrp' => 995.0,
                'gross' => 5850.6,
                'disc_p' => 2.0,
                'disc_amt' => 117.01,
                'gst_p' => 18.0,
                'gst_amt' => 1032.05,
            ],
            [
                'sno' => 37,
                'code' => '7522',
                'desc' => 'GN 5 Inch Stick Roll 220G',
                'exp' => '30-03-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 196.0,
                'sell' => 400.0,
                'mrp' => 400.0,
                'gross' => 2352.0,
                'disc_p' => 2.0,
                'disc_amt' => 47.04,
                'gst_p' => 18.0,
                'gst_amt' => 414.89,
            ],
            [
                'sno' => 38,
                'code' => '7543',
                'desc' => 'GN 5 Inch Stick Roll 500G',
                'exp' => '30-06-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 428.75,
                'sell' => 875.0,
                'mrp' => 875.0,
                'gross' => 5145.0,
                'disc_p' => 2.0,
                'disc_amt' => 102.9,
                'gst_p' => 18.0,
                'gst_amt' => 907.58,
            ],
            [
                'sno' => 39,
                'code' => '7483',
                'desc' => 'Goodies Treat Mix Stick 500G',
                'exp' => '30-06-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 318.5,
                'sell' => 650.0,
                'mrp' => 650.0,
                'gross' => 3822.0,
                'disc_p' => 2.0,
                'disc_amt' => 76.44,
                'gst_p' => 18.0,
                'gst_amt' => 674.2,
            ],
            [
                'sno' => 40,
                'code' => '7472',
                'desc' => 'Goodies Treat Cut Bone 500G',
                'exp' => '30-03-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 318.5,
                'sell' => 650.0,
                'mrp' => 650.0,
                'gross' => 3822.0,
                'disc_p' => 2.0,
                'disc_amt' => 76.44,
                'gst_p' => 18.0,
                'gst_amt' => 674.2,
            ],
            [
                'sno' => 41,
                'code' => '7473',
                'desc' => 'Goodies Treat Lamb 500G',
                'exp' => '30-06-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 318.5,
                'sell' => 650.0,
                'mrp' => 650.0,
                'gross' => 3822.0,
                'disc_p' => 2.0,
                'disc_amt' => 76.44,
                'gst_p' => 18.0,
                'gst_amt' => 674.2,
            ],
            [
                'sno' => 42,
                'code' => '7475',
                'desc' => 'Goodies Treat Chlorophyll 500G',
                'exp' => '30-03-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 318.5,
                'sell' => 650.0,
                'mrp' => 650.0,
                'gross' => 3822.0,
                'disc_p' => 2.0,
                'disc_amt' => 76.44,
                'gst_p' => 18.0,
                'gst_amt' => 674.2,
            ],
            [
                'sno' => 43,
                'code' => '7496',
                'desc' => 'Goodies Spira Twist Sticks BBQ and Lamb 450G',
                'exp' => '30-06-2028',
                'qty' => 6.0,
                'free' => 0.0,
                'cost' => 355.25,
                'sell' => 725.0,
                'mrp' => 725.0,
                'gross' => 2131.5,
                'disc_p' => 2.0,
                'disc_amt' => 42.63,
                'gst_p' => 18.0,
                'gst_amt' => 376.0,
            ],
            [
                'sno' => 44,
                'code' => '7497',
                'desc' => 'Goodies Spira Twist Sticks Chicken Liver Bacon 450G',
                'exp' => '30-06-2028',
                'qty' => 6.0,
                'free' => 0.0,
                'cost' => 355.25,
                'sell' => 725.0,
                'mrp' => 725.0,
                'gross' => 2131.5,
                'disc_p' => 2.0,
                'disc_amt' => 42.63,
                'gst_p' => 18.0,
                'gst_amt' => 376.0,
            ],
            [
                'sno' => 45,
                'code' => '7494',
                'desc' => 'Goodies Spiral twist sticks chlorophyll and mint 450G',
                'exp' => '30-06-2028',
                'qty' => 6.0,
                'free' => 0.0,
                'cost' => 355.25,
                'sell' => 725.0,
                'mrp' => 725.0,
                'gross' => 2131.5,
                'disc_p' => 2.0,
                'disc_amt' => 42.63,
                'gst_p' => 18.0,
                'gst_amt' => 376.0,
            ],
            [
                'sno' => 46,
                'code' => '7495',
                'desc' => 'Goodies Spira Twist Sticks Milk and Cheese 450G',
                'exp' => '30-06-2028',
                'qty' => 6.0,
                'free' => 0.0,
                'cost' => 355.25,
                'sell' => 725.0,
                'mrp' => 725.0,
                'gross' => 2131.5,
                'disc_p' => 2.0,
                'disc_amt' => 42.63,
                'gst_p' => 18.0,
                'gst_amt' => 376.0,
            ],
            [
                'sno' => 47,
                'code' => '7744',
                'desc' => 'First Bark Fruity Blast Mix Fruit 225G',
                'exp' => '30-06-2028',
                'qty' => 24.0,
                'free' => 0.0,
                'cost' => 171.5,
                'sell' => 350.0,
                'mrp' => 350.0,
                'gross' => 4116.0,
                'disc_p' => 2.0,
                'disc_amt' => 82.32,
                'gst_p' => 18.0,
                'gst_amt' => 726.06,
            ],
            [
                'sno' => 48,
                'code' => '7741',
                'desc' => 'First Bark Fruity Blast Banana Twist 225G',
                'exp' => '30-04-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 171.5,
                'sell' => 350.0,
                'mrp' => 350.0,
                'gross' => 2058.0,
                'disc_p' => 2.0,
                'disc_amt' => 41.16,
                'gst_p' => 18.0,
                'gst_amt' => 363.03,
            ],
            [
                'sno' => 49,
                'code' => '7742',
                'desc' => 'First Bark Fruity Blast Blueberry Zing 225G',
                'exp' => '30-06-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 171.5,
                'sell' => 350.0,
                'mrp' => 350.0,
                'gross' => 2058.0,
                'disc_p' => 2.0,
                'disc_amt' => 41.16,
                'gst_p' => 18.0,
                'gst_amt' => 363.03,
            ],
            [
                'sno' => 50,
                'code' => '7743',
                'desc' => 'First Bark Fruity Blast Mango Bites 225G',
                'exp' => '30-06-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 171.5,
                'sell' => 350.0,
                'mrp' => 350.0,
                'gross' => 2058.0,
                'disc_p' => 2.0,
                'disc_amt' => 41.16,
                'gst_p' => 18.0,
                'gst_amt' => 363.03,
            ],
            [
                'sno' => 51,
                'code' => '7745',
                'desc' => 'First Bark Fruity Blast Strawberry Bust 225G',
                'exp' => '30-06-2028',
                'qty' => 12.0,
                'free' => 0.0,
                'cost' => 171.5,
                'sell' => 350.0,
                'mrp' => 350.0,
                'gross' => 2058.0,
                'disc_p' => 2.0,
                'disc_amt' => 41.16,
                'gst_p' => 18.0,
                'gst_amt' => 363.03,
            ],
            [
                'sno' => 52,
                'code' => '10409',
                'desc' => 'JH Meat as Meals Grilled liver 500gm',
                'exp' => '30-05-2027',
                'qty' => 6.0,
                'free' => 0.0,
                'cost' => 538.51,
                'sell' => 1099.0,
                'mrp' => 1099.0,
                'gross' => 3231.06,
                'disc_p' => 2.0,
                'disc_amt' => 64.62,
                'gst_p' => 18.0,
                'gst_amt' => 569.96,
            ],
            [
                'sno' => 53,
                'code' => '7776',
                'desc' => 'JH Meat as Meals Chicken 45gm',
                'exp' => '30-06-2027',
                'qty' => 6.0,
                'free' => 0.0,
                'cost' => 50.29,
                'sell' => 135.0,
                'mrp' => 135.0,
                'gross' => 301.74,
                'disc_p' => 99.99,
                'disc_amt' => 301.71,
                'gst_p' => 18.0,
                'gst_amt' => 0.01,
            ],
                ];

                $codes = array_column($grnRaw, 'code');
                $itemsMap = Item::whereIn('item_code', $codes)->get()->keyBy('item_code');

                $itemIds = $itemsMap->pluck('id')->toArray();
                $stocksBefore = DB::table('item_stocks')->whereIn('item_id', $itemIds)->pluck('quantity', 'item_id')->toArray();
                $stockLedgerBefore = DB::table('stock_ledger')->count();

                // Clear any existing items for this invoice
                DB::table('purchase_invoice_items')->where('purchase_invoice_id', $purchaseInvoice->id)->delete();

                $piItems = [];
                $totalQty = 0;
                $totalGst = 0;
                $totalNet = 0;
                $totalDisc = 0;

                foreach ($grnRaw as $row) {
                    $code = (string)$row['code'];
                    if (!isset($itemsMap[$code])) {
                        throw new \Exception("Item code {$code} not found in items table!");
                    }
                    $item = $itemsMap[$code];

                    $expDate = null;
                    if (!empty($row['exp']) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $row['exp'])) {
                        $parts = explode('-', $row['exp']);
                        $expDate = "{$parts[2]}-{$parts[1]}-{$parts[0]}";
                    }

                    $qty = (float)$row['qty'];
                    $free = (float)($row['free'] ?? 0);
                    $cost = (float)$row['cost'];
                    $sell = (float)$row['sell'];
                    $mrp = (float)$row['mrp'];
                    $discPercent = (float)$row['disc_p'];
                    $discAmount = (float)$row['disc_amt'];
                    $gstPercent = (float)$row['gst_p'];
                    $gstTaxAmt = (float)$row['gst_amt'];

                    $gross = (float)$row['gross'];
                    $taxable = $gross - $discAmount;
                    $net = round($taxable + $gstTaxAmt, 2);

                    $totalQty += $qty + $free;
                    $totalDisc += $discAmount;
                    $totalGst += $gstTaxAmt;
                    $totalNet += $net;

                    $piItems[] = [
                        'purchase_invoice_id' => $purchaseInvoice->id,
                        'item_id' => $item->id,
                        'batch_no' => $row['exp'] ?: null,
                        'exp_date' => $expDate,
                        'qty' => $qty,
                        'free_qty' => $free,
                        'cost_price' => $cost,
                        'effective_cost' => $cost,
                        'sell_price' => $sell,
                        'mrp' => $mrp,
                        'disc_percent' => $discPercent,
                        'disc_amount' => $discAmount,
                        'gst_percent' => $gstPercent,
                        'gst_tax_amount' => $gstTaxAmt,
                        'cgst_amount' => 0.00,
                        'sgst_amount' => 0.00,
                        'igst_amount' => $gstTaxAmt, // Interstate
                        'net_amount' => $net,
                        'created_at' => Carbon::parse('2026-08-18 10:00:00'),
                        'updated_at' => Carbon::now(),
                    ];
                }

                DB::table('purchase_invoice_items')->insert($piItems);
                $this->info("    [+] Inserted " . count($piItems) . " lines into purchase_invoice_items.");

                // Update purchase invoice header totals
                DB::table('purchase_invoices')->where('id', $purchaseInvoice->id)->update([
                    'total_qty' => $totalQty,
                    'total_gst' => $totalGst,
                    'disc_amount' => $totalDisc,
                    'total' => 199657.00, // Exact TruePOS total
                    'round_off' => 0.00,
                    'remarks' => 'COUNT BY KAVERI  PURCHASE BY KAVERI',
                    'updated_at' => Carbon::now(),
                ]);

                // Verify stock for all 53 items
                $stocksAfter = DB::table('item_stocks')->whereIn('item_id', $itemIds)->pluck('quantity', 'item_id')->toArray();
                $stockLedgerAfter = DB::table('stock_ledger')->count();

                foreach ($itemIds as $id) {
                    $before = (float)($stocksBefore[$id] ?? 0);
                    $after = (float)($stocksAfter[$id] ?? 0);
                    if ($before !== $after) {
                        throw new \Exception("FATAL: Stock changed unexpectedly for item ID {$id}!");
                    }
                }

                if ($stockLedgerAfter !== $stockLedgerBefore) {
                    throw new \Exception("FATAL: Stock Ledger rows changed unexpectedly!");
                }

                $this->line("    Total Qty: {$totalQty} units | GST: ₹" . number_format($totalGst, 2) . " | Net: ₹" . number_format(199657, 2));
            }

            DB::commit();
            $this->info("\n[SUCCESS] All historical items populated with 100% verified ZERO stock impact!");
            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\n[ERROR] Rollback executed: " . $e->getMessage());
            return 1;
        }
    }
}
