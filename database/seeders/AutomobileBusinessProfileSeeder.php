<?php

namespace Database\Seeders;

use App\Models\PosSetting;
use Illuminate\Database\Seeder;

class AutomobileBusinessProfileSeeder extends Seeder
{
    public function run(): void
    {
        PosSetting::set('business_type', 'automobile');
        if ($this->command) {
            $this->command->info('Business profile successfully configured: ' . PosSetting::get('business_type'));
        }
    }
}
