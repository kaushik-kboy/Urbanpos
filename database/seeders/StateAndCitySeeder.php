<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StateAndCitySeeder extends Seeder
{
    public function run(): void
    {
        $states = [
            ['name' => 'Gujarat', 'code' => 'GJ'],
            ['name' => 'Rajasthan', 'code' => 'RJ'],
            ['name' => 'Maharashtra', 'code' => 'MH'],
            ['name' => 'Delhi', 'code' => 'DL'],
            ['name' => 'Madhya Pradesh', 'code' => 'MP'],
            ['name' => 'Uttar Pradesh', 'code' => 'UP'],
            ['name' => 'Karnataka', 'code' => 'KA'],
            ['name' => 'Tamil Nadu', 'code' => 'TN'],
            ['name' => 'Telangana', 'code' => 'TS'],
            ['name' => 'Andhra Pradesh', 'code' => 'AP'],
            ['name' => 'Punjab', 'code' => 'PB'],
            ['name' => 'Haryana', 'code' => 'HR'],
            ['name' => 'West Bengal', 'code' => 'WB'],
            ['name' => 'Kerala', 'code' => 'KL'],
            ['name' => 'Goa', 'code' => 'GA'],
            ['name' => 'Bihar', 'code' => 'BR'],
            ['name' => 'Odisha', 'code' => 'OD'],
            ['name' => 'Assam', 'code' => 'AS'],
            ['name' => 'Chhattisgarh', 'code' => 'CG'],
            ['name' => 'Jharkhand', 'code' => 'JH'],
            ['name' => 'Uttarakhand', 'code' => 'UK'],
            ['name' => 'Himachal Pradesh', 'code' => 'HP'],
            ['name' => 'Jammu and Kashmir', 'code' => 'JK'],
            ['name' => 'Chandigarh', 'code' => 'CH'],
            ['name' => 'Puducherry', 'code' => 'PY'],
            ['name' => 'Andaman and Nicobar Islands', 'code' => 'AN'],
            ['name' => 'Ladakh', 'code' => 'LA'],
            ['name' => 'Lakshadweep', 'code' => 'LD'],
            ['name' => 'Dadra and Nagar Haveli and Daman and Diu', 'code' => 'DN'],
            ['name' => 'Manipur', 'code' => 'MN'],
            ['name' => 'Meghalaya', 'code' => 'ML'],
            ['name' => 'Mizoram', 'code' => 'MZ'],
            ['name' => 'Nagaland', 'code' => 'NL'],
            ['name' => 'Sikkim', 'code' => 'SK'],
            ['name' => 'Tripura', 'code' => 'TR'],
            ['name' => 'Arunachal Pradesh', 'code' => 'AR'],
        ];

        $stateModelMap = [];
        foreach ($states as $st) {
            $stateModelMap[$st['name']] = State::firstOrCreate(
                ['name' => $st['name']],
                ['code' => $st['code'], 'country' => 'India', 'status' => true]
            );
        }

        $gujaratId = $stateModelMap['Gujarat']->id ?? null;
        $maharashtraId = $stateModelMap['Maharashtra']->id ?? null;
        $rajasthanId = $stateModelMap['Rajasthan']->id ?? null;
        $delhiId = $stateModelMap['Delhi']->id ?? null;
        $karnatakaId = $stateModelMap['Karnataka']->id ?? null;

        $cities = [
            // Gujarat
            ['name' => 'Ahmedabad', 'state_id' => $gujaratId],
            ['name' => 'Gandhinagar', 'state_id' => $gujaratId],
            ['name' => 'Surat', 'state_id' => $gujaratId],
            ['name' => 'Vadodara', 'state_id' => $gujaratId],
            ['name' => 'Rajkot', 'state_id' => $gujaratId],
            ['name' => 'Bhavnagar', 'state_id' => $gujaratId],
            ['name' => 'Jamnagar', 'state_id' => $gujaratId],
            ['name' => 'Junagadh', 'state_id' => $gujaratId],
            ['name' => 'Anand', 'state_id' => $gujaratId],
            ['name' => 'Navsari', 'state_id' => $gujaratId],
            ['name' => 'Morbi', 'state_id' => $gujaratId],
            ['name' => 'Mehsana', 'state_id' => $gujaratId],
            ['name' => 'Bhuj', 'state_id' => $gujaratId],
            ['name' => 'Vapi', 'state_id' => $gujaratId],
            ['name' => 'Bharuch', 'state_id' => $gujaratId],
            ['name' => 'Modasa', 'state_id' => $gujaratId],
            ['name' => 'Palanpur', 'state_id' => $gujaratId],
            ['name' => 'Valsad', 'state_id' => $gujaratId],
            ['name' => 'Gondal', 'state_id' => $gujaratId],
            ['name' => 'Veraval', 'state_id' => $gujaratId],
            ['name' => 'Godhra', 'state_id' => $gujaratId],
            ['name' => 'Patan', 'state_id' => $gujaratId],
            ['name' => 'Dahod', 'state_id' => $gujaratId],
            ['name' => 'Botad', 'state_id' => $gujaratId],
            ['name' => 'Amreli', 'state_id' => $gujaratId],
            ['name' => 'Deesa', 'state_id' => $gujaratId],
            ['name' => 'Jetpur', 'state_id' => $gujaratId],

            // Maharashtra
            ['name' => 'Mumbai', 'state_id' => $maharashtraId],
            ['name' => 'Pune', 'state_id' => $maharashtraId],
            ['name' => 'Nagpur', 'state_id' => $maharashtraId],
            ['name' => 'Thane', 'state_id' => $maharashtraId],
            ['name' => 'Nashik', 'state_id' => $maharashtraId],

            // Rajasthan
            ['name' => 'Jaipur', 'state_id' => $rajasthanId],
            ['name' => 'Jodhpur', 'state_id' => $rajasthanId],
            ['name' => 'Udaipur', 'state_id' => $rajasthanId],
            ['name' => 'Kota', 'state_id' => $rajasthanId],

            // Delhi
            ['name' => 'New Delhi', 'state_id' => $delhiId],

            // Karnataka
            ['name' => 'Bengaluru', 'state_id' => $karnatakaId],
        ];

        $ahmedabadCity = null;
        foreach ($cities as $ct) {
            $created = City::firstOrCreate(
                ['name' => $ct['name'], 'state_id' => $ct['state_id']],
                ['status' => true]
            );
            if ($ct['name'] === 'Ahmedabad') {
                $ahmedabadCity = $created;
            }
        }

        // Link existing areas that have null city_id to Ahmedabad by default
        if ($ahmedabadCity) {
            DB::table('areas')->whereNull('city_id')->update(['city_id' => $ahmedabadCity->id]);
        }
    }
}
