<?php

namespace Database\Seeders;

use App\Models\TenderType;
use Illuminate\Database\Seeder;

class TenderTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Cash',   'type' => 'Cash',   'mode' => 'Manual', 'service_applicable' => false, 'mandate_refno' => false, 'service_charge_perc' => 0],
            ['name' => 'Credit', 'type' => 'Credit', 'mode' => 'Manual', 'service_applicable' => false, 'mandate_refno' => false, 'service_charge_perc' => 0],
            ['name' => 'Card',   'type' => 'Card',   'mode' => 'Manual', 'service_applicable' => false, 'mandate_refno' => true,  'service_charge_perc' => 0],
            ['name' => 'Wallet', 'type' => 'Wallet', 'mode' => 'Manual', 'service_applicable' => false, 'mandate_refno' => false, 'service_charge_perc' => 0],
            ['name' => 'RRN',    'type' => 'Finance','mode' => 'Manual', 'service_applicable' => false, 'mandate_refno' => false, 'service_charge_perc' => 0],
            ['name' => 'UPI',    'type' => 'Wallet', 'mode' => 'Manual', 'service_applicable' => false, 'mandate_refno' => false, 'service_charge_perc' => 0],
        ];

        foreach ($types as $t) {
            $record = TenderType::firstOrCreate(['name' => $t['name']], array_merge($t, ['status' => true]));

            if ($t['name'] === 'Wallet') {
                $walletValues = ['PINELAB', 'PAYTM', 'PHONEPE', 'GPAY', 'BHARATPE', 'OTHER'];
                foreach ($walletValues as $val) {
                    \App\Models\TenderTypeValue::firstOrCreate([
                        'tender_type_id' => $record->id,
                        'name' => $val,
                    ], ['status' => true]);
                }
            }
        }
    }
}
