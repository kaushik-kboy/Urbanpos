<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesBill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomobilePosTest extends TestCase
{
    use RefreshDatabase;

    public function test_automobile_sales_bill_saves_vehicle_and_service_charges(): void
    {
        $customer = Customer::first() ?? Customer::create([
            'name' => 'Automobile Test Customer',
            'phone' => '9829012345',
        ]);

        $branch = Branch::first() ?? Branch::create([
            'name' => 'Main Workshop Branch',
            'code' => 'WS01',
        ]);

        $bill = SalesBill::create([
            'bill_number' => 'AUTO-TEST-' . rand(1000, 9999),
            'bill_date' => now(),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'vehicle_no' => 'RJ-14-CB-1234',
            'vehicle_model' => 'Hyundai Creta SX',
            'odometer_km' => 42500,
            'mechanic_name' => 'Ramesh Mechanic',
            'service_charge' => 350.00,
            'total' => 7100.00,
        ]);

        $this->assertDatabaseHas('sales_bills', [
            'id' => $bill->id,
            'vehicle_no' => 'RJ-14-CB-1234',
            'vehicle_model' => 'Hyundai Creta SX',
            'odometer_km' => 42500,
            'mechanic_name' => 'Ramesh Mechanic',
            'service_charge' => 350.00,
        ]);
    }

    public function test_automobile_item_saves_part_number_and_compatibility(): void
    {
        $item = Item::create([
            'name' => 'Bosch Premium Oil Filter',
            'item_code' => 'AUTO-PART-' . rand(1000, 9999),
            'part_number' => 'BOSCH-OF-0941',
            'car_compatibility' => 'Swift, Baleno, Dzire 2018-2023',
            'warranty_months' => 6,
            'cost_price' => 250.00,
            'sell_price' => 450.00,
            'mrp' => 450.00,
        ]);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'part_number' => 'BOSCH-OF-0941',
            'car_compatibility' => 'Swift, Baleno, Dzire 2018-2023',
            'warranty_months' => 6,
        ]);
    }

    public function test_standard_retail_bill_without_vehicle_details_defaults_safely(): void
    {
        $customer = Customer::create(['name' => 'Retail Customer', 'phone' => '9829000000']);
        $branch = Branch::create(['name' => 'Main Branch', 'code' => 'BR01']);

        $bill = SalesBill::create([
            'bill_number' => 'RETAIL-TEST-' . rand(1000, 9999),
            'bill_date' => now(),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total' => 500.00,
        ]);

        $this->assertNull($bill->vehicle_model);
        $this->assertNull($bill->mechanic_name);
        $this->assertEquals(0.00, $bill->service_charge);
    }

    public function test_pos_item_list_searches_by_car_compatibility_and_part_number(): void
    {
        $user = User::create([
            'name' => 'Auto Cashier',
            'email' => 'cashier@autocare.com',
            'password' => bcrypt('password123'),
        ]);
        $this->actingAs($user);

        $branch = Branch::create(['name' => 'Car Care Workshop', 'code' => 'CC01']);

        $item1 = Item::create([
            'name' => 'NGK Laser Iridium Spark Plug',
            'item_code' => 'SPARK-001',
            'part_number' => 'NGK-IZFR6K-11S',
            'car_compatibility' => 'Honda City, Civic, Jazz',
            'warranty_months' => 12,
            'cost_price' => 400.00,
            'sell_price' => 650.00,
            'mrp' => 650.00,
            'status' => true,
            'allow_negative_stock' => true,
        ]);

        $item2 = Item::create([
            'name' => 'Bosch Front Brake Pads',
            'item_code' => 'BRAKE-002',
            'part_number' => 'BOSCH-BP-449',
            'car_compatibility' => 'Maruti Swift, Dzire, Baleno',
            'warranty_months' => 6,
            'cost_price' => 800.00,
            'sell_price' => 1200.00,
            'mrp' => 1200.00,
            'status' => true,
            'allow_negative_stock' => true,
        ]);

        // 1. Search by Car Name: "Swift"
        $response = $this->getJson(route('sales.sales-bills.item-list', [
            'search' => 'Swift',
            'branch_id' => $branch->id,
            'show_all' => 1,
        ]));

        $response->assertStatus(200);
        $items = $response->json('items');
        $this->assertNotEmpty($items);
        $this->assertEquals('Bosch Front Brake Pads', $items[0]['name']);
        $this->assertEquals('BOSCH-BP-449', $items[0]['part_number']);
        $this->assertEquals('Maruti Swift, Dzire, Baleno', $items[0]['car_compatibility']);

        // 2. Search by OEM Part Number: "NGK-IZFR6K-11S"
        $responsePart = $this->getJson(route('sales.sales-bills.item-list', [
            'search' => 'NGK-IZFR6K-11S',
            'branch_id' => $branch->id,
            'show_all' => 1,
        ]));

        $responsePart->assertStatus(200);
        $itemsPart = $responsePart->json('items');
        $this->assertNotEmpty($itemsPart);
        $this->assertEquals('NGK Laser Iridium Spark Plug', $itemsPart[0]['name']);
    }

    public function test_pos_lookup_item_finds_by_exact_oem_part_number(): void
    {
        $user = User::create([
            'name' => 'Auto Cashier 2',
            'email' => 'cashier2@autocare.com',
            'password' => bcrypt('password123'),
        ]);
        $this->actingAs($user);

        $branch = Branch::create(['name' => 'Car Care Workshop', 'code' => 'CC02']);

        $item = Item::create([
            'name' => 'Purolator Air Filter',
            'item_code' => 'AIR-003',
            'part_number' => 'PUR-AF-7721',
            'car_compatibility' => 'Hyundai Creta, Kia Seltos',
            'warranty_months' => 6,
            'cost_price' => 200.00,
            'sell_price' => 380.00,
            'mrp' => 380.00,
            'status' => true,
            'allow_negative_stock' => true,
        ]);

        $response = $this->getJson(route('sales.sales-bills.lookup-item', [
            'query' => 'PUR-AF-7721',
            'branch_id' => $branch->id,
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'found' => true,
            'item' => [
                'name' => 'Purolator Air Filter',
                'part_number' => 'PUR-AF-7721',
                'car_compatibility' => 'Hyundai Creta, Kia Seltos',
                'warranty_months' => 6,
            ],
        ]);
    }
}
