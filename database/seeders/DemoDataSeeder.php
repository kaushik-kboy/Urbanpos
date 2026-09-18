<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\FinancialYear;
use App\Models\GstTax;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemStock;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Register;
use App\Models\SalesBill;
use App\Models\SalesBillItem;
use App\Models\SalesBillPayment;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\StockLedger;
use App\Models\Supplier;
use App\Models\TenderType;
use App\Models\TillSession;
use App\Models\Uom;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->seedMasters();
            $this->seedOperations();
        });
    }

    private function seedMasters(): void
    {
        // 1. GST Taxes
        GstTax::firstOrCreate(['percentage' => 0], ['description' => 'GST 0% (Exempt)', 'status' => 1]);
        GstTax::firstOrCreate(['percentage' => 5], ['description' => 'GST 5%', 'status' => 1]);
        GstTax::firstOrCreate(['percentage' => 12], ['description' => 'GST 12%', 'status' => 1]);
        GstTax::firstOrCreate(['percentage' => 18], ['description' => 'GST 18%', 'status' => 1]);
        GstTax::firstOrCreate(['percentage' => 28], ['description' => 'GST 28%', 'status' => 1]);

        // 2. UOMs
        Uom::firstOrCreate(['name' => 'PCS'], ['alias' => 'Pieces', 'status' => 1]);
        Uom::firstOrCreate(['name' => 'KG'], ['alias' => 'Kilogram', 'status' => 1]);
        Uom::firstOrCreate(['name' => 'BOX'], ['alias' => 'Box', 'status' => 1]);
        Uom::firstOrCreate(['name' => 'PACK'], ['alias' => 'Pack', 'status' => 1]);
        Uom::firstOrCreate(['name' => 'BOTTLE'], ['alias' => 'Bottle', 'status' => 1]);

        // 3. Item Categories
        ItemCategory::firstOrCreate(['name' => 'Dog Food'], ['is_mandatory' => 0, 'status' => 1]);
        ItemCategory::firstOrCreate(['name' => 'Cat Food'], ['is_mandatory' => 0, 'status' => 1]);
        ItemCategory::firstOrCreate(['name' => 'Pet Grooming'], ['is_mandatory' => 0, 'status' => 1]);
        ItemCategory::firstOrCreate(['name' => 'Pet Accessories'], ['is_mandatory' => 0, 'status' => 1]);
        ItemCategory::firstOrCreate(['name' => 'Healthcare'], ['is_mandatory' => 0, 'status' => 1]);

        // 4. Brands
        Brand::firstOrCreate(['name' => 'Royal Canin'], ['prefix' => 'RC', 'status' => 1]);
        Brand::firstOrCreate(['name' => 'Pedigree'], ['prefix' => 'PD', 'status' => 1]);
        Brand::firstOrCreate(['name' => 'Whiskas'], ['prefix' => 'WH', 'status' => 1]);
        Brand::firstOrCreate(['name' => 'Himalaya'], ['prefix' => 'HM', 'status' => 1]);
        Brand::firstOrCreate(['name' => 'Drools'], ['prefix' => 'DR', 'status' => 1]);

        // 5. Suppliers
        Supplier::firstOrCreate(
            ['name' => 'Mars Petcare India Pvt Ltd'],
            [
                'currency' => 'INR',
                'purchase_type' => 'Local',
                'purchase_mode' => 'Credit',
                'credit_limit' => 500000,
                'credit_balance' => 0,
                'credit_days' => 30,
                'status' => 1,
                'gst_type' => 'Regular',
                'mail_type' => 'None',
                'address' => 'Plot 42, GIDC Industrial Estate',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'postal_code' => '380009',
                'country' => 'India',
                'phone' => '07923456789',
                'mobile' => '9825012345',
                'email' => 'orders@marspetcare.in',
                'gst_no' => '24AAACM1234F1Z1',
                'pan_no' => 'AAACM1234F',
            ]
        );

        Supplier::firstOrCreate(
            ['name' => 'Royal Pet Distribution Co'],
            [
                'currency' => 'INR',
                'purchase_type' => 'Local',
                'purchase_mode' => 'Credit',
                'credit_limit' => 250000,
                'credit_balance' => 0,
                'credit_days' => 15,
                'status' => 1,
                'gst_type' => 'Regular',
                'mail_type' => 'None',
                'address' => 'Sarkhej Highway',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'postal_code' => '382210',
                'country' => 'India',
                'phone' => '07998765432',
                'mobile' => '9825098765',
                'email' => 'supply@royalpet.in',
                'gst_no' => '24BBBCR5678K1Z2',
                'pan_no' => 'BBBCR5678K',
            ]
        );

        // 6. Customers
        Customer::firstOrCreate(
            ['name' => 'Walk-in Customer'],
            [
                'sales_type' => 'Local',
                'payment_mode' => 'Cash Only',
                'credit_limit' => 0,
                'credit_balance' => 0,
                'monthly_credit_balance' => 0,
                'credit_days' => 0,
                'status' => 1,
                'gst_type' => 'Un Register',
                'sms_consent' => 0,
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'country' => 'India',
                'mobile' => '9999999999',
                'customer_type' => 'RETAIL INVOICE',
            ]
        );

        Customer::firstOrCreate(
            ['mobile' => '9876500001'],
            [
                'name' => 'Rahul Sharma',
                'sales_type' => 'Local',
                'payment_mode' => 'Both Cash and Credit',
                'credit_limit' => 25000,
                'credit_balance' => 0,
                'monthly_credit_balance' => 0,
                'credit_days' => 15,
                'status' => 1,
                'gst_type' => 'Un Register',
                'sms_consent' => 1,
                'address1' => 'B-402, Shivam Heights, Bodakdev',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'country' => 'India',
                'postal_code' => '380054',
                'email' => 'rahul.sharma@example.com',
                'customer_type' => 'RETAIL INVOICE',
            ]
        );

        Customer::firstOrCreate(
            ['mobile' => '9876500002'],
            [
                'name' => 'Priya Patel',
                'sales_type' => 'Local',
                'payment_mode' => 'Both Cash and Credit',
                'credit_limit' => 50000,
                'credit_balance' => 0,
                'monthly_credit_balance' => 0,
                'credit_days' => 30,
                'status' => 1,
                'gst_type' => 'Regular',
                'sms_consent' => 1,
                'address1' => '12, Sunrise Park, Vastrapur',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'country' => 'India',
                'postal_code' => '380015',
                'email' => 'priya.patel@example.com',
                'gst_no' => '24AAPPP1122D1Z5',
                'customer_type' => 'TAX INVOICE',
            ]
        );

        // 7. Products (Items)
        $gst18 = GstTax::where('percentage', 18)->first();
        $gst12 = GstTax::where('percentage', 12)->first();
        $gst5 = GstTax::where('percentage', 5)->first();

        $bRoyal = Brand::where('name', 'Royal Canin')->first();
        $bPed = Brand::where('name', 'Pedigree')->first();
        $bWhis = Brand::where('name', 'Whiskas')->first();
        $bHim = Brand::where('name', 'Himalaya')->first();
        $bDro = Brand::where('name', 'Drools')->first();

        $supMars = Supplier::where('name', 'Mars Petcare India Pvt Ltd')->first();
        $supRoy = Supplier::where('name', 'Royal Pet Distribution Co')->first();

        $items = [
            [
                'item_code' => 'RC-MAXI-15KG',
                'ean_upc_code' => '890103000001',
                'name' => 'Royal Canin Maxi Adult 15kg',
                'brand_id' => $bRoyal?->id,
                'supplier_id' => $supMars?->id,
                'product_type' => 'Standard',
                'cost_price' => 4800,
                'landing_cost' => 4800,
                'sell_price' => 5800,
                'mrp' => 6500,
                'status' => 1,
                'store_pickup' => 1,
                'tax_inclusive' => 1,
                'batch_expiry_details' => 'Optional',
                'allow_negative_stock' => 1,
                'gst_tax_id' => $gst18?->id,
                'hsn_code' => '2309',
            ],
            [
                'item_code' => 'PD-ADULT-3KG',
                'ean_upc_code' => '890103000002',
                'name' => 'Pedigree Adult Meat & Rice 3kg',
                'brand_id' => $bPed?->id,
                'supplier_id' => $supMars?->id,
                'product_type' => 'Standard',
                'cost_price' => 420,
                'landing_cost' => 420,
                'sell_price' => 540,
                'mrp' => 600,
                'status' => 1,
                'store_pickup' => 1,
                'tax_inclusive' => 1,
                'batch_expiry_details' => 'Optional',
                'allow_negative_stock' => 1,
                'gst_tax_id' => $gst18?->id,
                'hsn_code' => '2309',
            ],
            [
                'item_code' => 'WH-FISH-1.2KG',
                'ean_upc_code' => '890103000003',
                'name' => 'Whiskas Ocean Fish 1.2kg',
                'brand_id' => $bWhis?->id,
                'supplier_id' => $supMars?->id,
                'product_type' => 'Standard',
                'cost_price' => 310,
                'landing_cost' => 310,
                'sell_price' => 400,
                'mrp' => 450,
                'status' => 1,
                'store_pickup' => 1,
                'tax_inclusive' => 1,
                'batch_expiry_details' => 'Optional',
                'allow_negative_stock' => 1,
                'gst_tax_id' => $gst12?->id,
                'hsn_code' => '2309',
            ],
            [
                'item_code' => 'HM-SHAMP-500ML',
                'ean_upc_code' => '890103000004',
                'name' => 'Himalaya Pet Grooming Shampoo 500ml',
                'brand_id' => $bHim?->id,
                'supplier_id' => $supRoy?->id,
                'product_type' => 'Standard',
                'cost_price' => 240,
                'landing_cost' => 240,
                'sell_price' => 340,
                'mrp' => 380,
                'status' => 1,
                'store_pickup' => 1,
                'tax_inclusive' => 1,
                'batch_expiry_details' => 'Optional',
                'allow_negative_stock' => 1,
                'gst_tax_id' => $gst18?->id,
                'hsn_code' => '3305',
            ],
            [
                'item_code' => 'DR-BONE-LRG',
                'ean_upc_code' => '890103000005',
                'name' => 'Drools Dog Chew Bone Large',
                'brand_id' => $bDro?->id,
                'supplier_id' => $supRoy?->id,
                'product_type' => 'Standard',
                'cost_price' => 75,
                'landing_cost' => 75,
                'sell_price' => 120,
                'mrp' => 150,
                'status' => 1,
                'store_pickup' => 1,
                'tax_inclusive' => 1,
                'batch_expiry_details' => 'Optional',
                'allow_negative_stock' => 1,
                'gst_tax_id' => $gst5?->id,
                'hsn_code' => '2309',
            ],
        ];

        foreach ($items as $itemData) {
            Item::firstOrCreate(['item_code' => $itemData['item_code']], $itemData);
        }
    }

    private function seedOperations(): void
    {
        $branch = Branch::first() ?? Branch::create(['name' => 'Motera Branch', 'code' => 'MOTERA', 'state' => 'Gujarat']);
        $user = User::first();
        $adminId = $user ? $user->id : 1;

        // Financial Year
        FinancialYear::firstOrCreate(
            ['name' => 'FY 2026-27'],
            [
                'start_date' => '2026-04-01',
                'end_date' => '2027-03-31',
                'is_locked' => false,
            ]
        );

        // Register
        $register = Register::firstOrCreate(
            ['branch_id' => $branch->id, 'name' => 'Counter 1 (Main POS)'],
            [
                'status' => 'Active',
                'product_type' => 'Standard',
                'inv_seq_no' => 100,
                'online_sales_allowed' => 1,
                'register_prefix' => 'REG1',
            ]
        );

        // Till Session
        $till = TillSession::firstOrCreate(
            ['register_id' => $register->id, 'status' => 'Open'],
            [
                'branch_id' => $branch->id,
                'user_id' => $adminId,
                'opening_cash' => 2000.00,
                'opened_at' => now()->subHours(4),
            ]
        );

        // Items
        $rcItem = Item::where('item_code', 'RC-MAXI-15KG')->firstOrFail();
        $pdItem = Item::where('item_code', 'PD-ADULT-3KG')->firstOrFail();
        $whItem = Item::where('item_code', 'WH-FISH-1.2KG')->firstOrFail();
        $hmItem = Item::where('item_code', 'HM-SHAMP-500ML')->firstOrFail();
        $drItem = Item::where('item_code', 'DR-BONE-LRG')->firstOrFail();

        // 1. Initial Opening Stocks
        $opening = [
            [$rcItem, 10, 4800],
            [$pdItem, 25, 420],
            [$whItem, 20, 310],
            [$hmItem, 15, 240],
            [$drItem, 30, 75],
        ];

        foreach ($opening as [$it, $qty, $cost]) {
            ItemStock::updateOrCreate(
                ['item_id' => $it->id, 'branch_id' => $branch->id],
                [
                    'quantity' => $qty,
                    'cost_price' => $cost,
                    'landing_cost' => $cost,
                    'sell_price' => $it->sell_price,
                    'mrp' => $it->mrp,
                ]
            );

            StockLedger::firstOrCreate(
                [
                    'item_id' => $it->id,
                    'branch_id' => $branch->id,
                    'movement_type' => 'OPENING',
                ],
                [
                    'qty_in' => $qty,
                    'qty_out' => 0,
                    'unit_cost' => $cost,
                    'value_in' => $qty * $cost,
                    'value_out' => 0,
                    'running_balance_qty' => $qty,
                    'running_balance_value' => $qty * $cost,
                    'user_id' => $adminId,
                    'document_date' => '2026-09-01',
                    'posted_at' => now()->subDays(10),
                ]
            );
        }

        // 2. Purchase Order
        $supMars = Supplier::where('name', 'Mars Petcare India Pvt Ltd')->firstOrFail();
        $po = PurchaseOrder::firstOrCreate(
            ['po_number' => 'PO-2026-0001'],
            [
                'po_date' => now()->subDays(5)->toDateString(),
                'supplier_id' => $supMars->id,
                'branch_id' => $branch->id,
                'purchase_type' => 'Local',
                'c_form' => 'No Forms',
                'item_disc_amount' => 0,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'freight' => 0,
                'round_off' => 0,
                'scheme_item_disc_amt' => 0,
                'other_disc_amt' => 0,
                'total_gst' => 5076.00,
                'total_extra_cess' => 0,
                'total_qty' => 15,
                'total_weight' => 0,
                'total' => 33276.00,
                'remarks' => 'Initial bulk order for dog food',
                'status' => 'Closed',
            ]
        );

        PurchaseOrderItem::firstOrCreate(
            ['purchase_order_id' => $po->id, 'item_id' => $pdItem->id],
            [
                'qty' => 10,
                'free_qty' => 0,
                'cost_price' => 420.00,
                'sell_price' => 540.00,
                'mrp' => 600.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18.00,
                'gst_tax_amount' => 756.00,
                'net_amount' => 4956.00,
                'received_qty' => 10,
            ]
        );

        PurchaseOrderItem::firstOrCreate(
            ['purchase_order_id' => $po->id, 'item_id' => $rcItem->id],
            [
                'qty' => 5,
                'free_qty' => 0,
                'cost_price' => 4800.00,
                'sell_price' => 5800.00,
                'mrp' => 6500.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18.00,
                'gst_tax_amount' => 4320.00,
                'net_amount' => 28320.00,
                'received_qty' => 5,
            ]
        );

        // 3. Purchase Invoice (Posted, adds stock)
        $pi = PurchaseInvoice::firstOrCreate(
            ['invoice_number' => 'PI-2026-0001'],
            [
                'invoice_date' => now()->subDays(4)->toDateString(),
                'supplier_id' => $supMars->id,
                'branch_id' => $branch->id,
                'purchase_order_id' => $po->id,
                'supplier_inv_no' => 'MARS/INV/9821',
                'supplier_inv_date' => now()->subDays(4)->toDateString(),
                'supplier_inv_amount' => 33276.00,
                'purchase_type' => 'Local',
                'c_form' => 'No Forms',
                'item_disc_amount' => 0,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'freight' => 0,
                'round_off' => 0,
                'scheme_item_disc_amt' => 0,
                'scheme_item_disc_percent' => 0,
                'other_disc_amt' => 0,
                'total_gst' => 5076.00,
                'total_cgst' => 2538.00,
                'total_sgst' => 2538.00,
                'total_igst' => 0,
                'total_extra_cess' => 0,
                'tcs_amount' => 0,
                'total_qty' => 15,
                'total_weight' => 0,
                'total' => 33276.00,
                'remarks' => 'Material received in excellent condition',
                'status' => 'Posted',
                'posting_key' => (string) Str::uuid(),
            ]
        );

        PurchaseInvoiceItem::firstOrCreate(
            ['purchase_invoice_id' => $pi->id, 'item_id' => $pdItem->id],
            [
                'qty' => 10,
                'free_qty' => 0,
                'cost_price' => 420.00,
                'sell_price' => 540.00,
                'mrp' => 600.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18.00,
                'gst_tax_amount' => 756.00,
                'cgst_amount' => 378.00,
                'sgst_amount' => 378.00,
                'igst_amount' => 0,
                'net_amount' => 4956.00,
            ]
        );

        PurchaseInvoiceItem::firstOrCreate(
            ['purchase_invoice_id' => $pi->id, 'item_id' => $rcItem->id],
            [
                'qty' => 5,
                'free_qty' => 0,
                'cost_price' => 4800.00,
                'sell_price' => 5800.00,
                'mrp' => 6500.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18.00,
                'gst_tax_amount' => 4320.00,
                'cgst_amount' => 2160.00,
                'sgst_amount' => 2160.00,
                'igst_amount' => 0,
                'net_amount' => 28320.00,
            ]
        );

        // Update stocks for purchase (+10 PD, +5 RC)
        $pdStock = ItemStock::where('item_id', $pdItem->id)->where('branch_id', $branch->id)->first();
        $rcStock = ItemStock::where('item_id', $rcItem->id)->where('branch_id', $branch->id)->first();

        $pdStock->increment('quantity', 10);
        $rcStock->increment('quantity', 5);

        StockLedger::firstOrCreate(
            ['item_id' => $pdItem->id, 'reference_type' => 'PurchaseInvoice', 'reference_id' => $pi->id],
            [
                'branch_id' => $branch->id,
                'movement_type' => 'PURCHASE_RECEIPT',
                'qty_in' => 10,
                'qty_out' => 0,
                'unit_cost' => 420.00,
                'value_in' => 4200.00,
                'value_out' => 0,
                'running_balance_qty' => $pdStock->quantity,
                'running_balance_value' => $pdStock->quantity * 420.00,
                'user_id' => $adminId,
                'document_date' => $pi->invoice_date,
                'posted_at' => now()->subDays(4),
            ]
        );

        StockLedger::firstOrCreate(
            ['item_id' => $rcItem->id, 'reference_type' => 'PurchaseInvoice', 'reference_id' => $pi->id],
            [
                'branch_id' => $branch->id,
                'movement_type' => 'PURCHASE_RECEIPT',
                'qty_in' => 5,
                'qty_out' => 0,
                'unit_cost' => 4800.00,
                'value_in' => 24000.00,
                'value_out' => 0,
                'running_balance_qty' => $rcStock->quantity,
                'running_balance_value' => $rcStock->quantity * 4800.00,
                'user_id' => $adminId,
                'document_date' => $pi->invoice_date,
                'posted_at' => now()->subDays(4),
            ]
        );

        // 4. Sales Bill 1: Walk-in Cash Sale (2x Pedigree + 2x Drools Bone)
        $custWalk = Customer::where('name', 'Walk-in Customer')->firstOrFail();
        $tenderCash = TenderType::where('name', 'Cash')->first() ?? TenderType::firstOrCreate(['name' => 'Cash'], ['type' => 'Cash', 'status' => 1]);
        $tenderUpi = TenderType::where('name', 'UPI')->first() ?? TenderType::firstOrCreate(['name' => 'Card'], ['type' => 'Card', 'status' => 1]);

        $sb1 = SalesBill::firstOrCreate(
            ['bill_number' => 'SB-2026-0001'],
            [
                'bill_date' => now()->subDays(2)->format('Y-m-d H:i:s'),
                'customer_id' => $custWalk->id,
                'branch_id' => $branch->id,
                'till_session_id' => $till->id,
                'invoice_type' => 'Retail Invoice',
                'delivery_type' => 'Delivered',
                'sales_type' => 'Local',
                'payment_type' => 'Cash',
                'item_disc_amount' => 0,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'round_off' => 0,
                'total_gst' => 176.17,
                'total_cgst' => 88.08,
                'total_sgst' => 88.09,
                'total_igst' => 0,
                'total_extra_cess' => 0,
                'gst_calamity_cess' => 0,
                'total_qty' => 4,
                'total_weight' => 0,
                'total' => 1320.00,
                'remarks' => 'Counter POS Cash Sale',
                'status' => 'Posted',
                'transport_mode' => 'Road',
                'vehicle_type' => 'Regular',
                'eway_status' => 'Not Generated',
                'einvoice_status' => 'Not Applicable',
                'posting_key' => (string) Str::uuid(),
            ]
        );

        // Pedigree 2x @ 540 = 1080 (incl 18% GST: base 915.25 + gst 164.75)
        SalesBillItem::firstOrCreate(
            ['sales_bill_id' => $sb1->id, 'item_id' => $pdItem->id],
            [
                'qty' => 2,
                'sell_price' => 540.00,
                'cost_at_sale' => 420.00,
                'mrp' => 600.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18.00,
                'gst_tax_amount' => 164.75,
                'cgst_amount' => 82.37,
                'sgst_amount' => 82.38,
                'igst_amount' => 0,
                'net_amount' => 1080.00,
            ]
        );

        // Drools Bone 2x @ 120 = 240 (incl 5% GST: base 228.57 + gst 11.43)
        SalesBillItem::firstOrCreate(
            ['sales_bill_id' => $sb1->id, 'item_id' => $drItem->id],
            [
                'qty' => 2,
                'sell_price' => 120.00,
                'cost_at_sale' => 75.00,
                'mrp' => 150.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 5.00,
                'gst_tax_amount' => 11.43,
                'cgst_amount' => 5.71,
                'sgst_amount' => 5.72,
                'igst_amount' => 0,
                'net_amount' => 240.00,
            ]
        );

        SalesBillPayment::firstOrCreate(
            ['sales_bill_id' => $sb1->id, 'tender_type_id' => $tenderCash->id],
            ['amount' => 1320.00]
        );

        // Deduct stock for Sale 1 (-2 PD, -2 DR)
        $pdStock->decrement('quantity', 2);
        $drStock = ItemStock::where('item_id', $drItem->id)->where('branch_id', $branch->id)->first();
        $drStock->decrement('quantity', 2);

        StockLedger::firstOrCreate(
            ['item_id' => $pdItem->id, 'reference_type' => 'SalesBill', 'reference_id' => $sb1->id],
            [
                'branch_id' => $branch->id,
                'movement_type' => 'SALE',
                'qty_in' => 0,
                'qty_out' => 2,
                'unit_cost' => 420.00,
                'value_in' => 0,
                'value_out' => 840.00,
                'running_balance_qty' => $pdStock->quantity,
                'running_balance_value' => $pdStock->quantity * 420.00,
                'user_id' => $adminId,
                'document_date' => $sb1->bill_date,
                'posted_at' => now()->subDays(2),
            ]
        );

        StockLedger::firstOrCreate(
            ['item_id' => $drItem->id, 'reference_type' => 'SalesBill', 'reference_id' => $sb1->id],
            [
                'branch_id' => $branch->id,
                'movement_type' => 'SALE',
                'qty_in' => 0,
                'qty_out' => 2,
                'unit_cost' => 75.00,
                'value_in' => 0,
                'value_out' => 150.00,
                'running_balance_qty' => $drStock->quantity,
                'running_balance_value' => $drStock->quantity * 75.00,
                'user_id' => $adminId,
                'document_date' => $sb1->bill_date,
                'posted_at' => now()->subDays(2),
            ]
        );

        // 5. Sales Bill 2: Rahul Sharma UPI Sale (1x Royal Canin 15kg + 1x Shampoo 500ml)
        $custRahul = Customer::where('name', 'Rahul Sharma')->firstOrFail();

        $sb2 = SalesBill::firstOrCreate(
            ['bill_number' => 'SB-2026-0002'],
            [
                'bill_date' => now()->subDay()->format('Y-m-d H:i:s'),
                'customer_id' => $custRahul->id,
                'branch_id' => $branch->id,
                'till_session_id' => $till->id,
                'invoice_type' => 'Retail Invoice',
                'delivery_type' => 'Delivered',
                'sales_type' => 'Local',
                'payment_type' => 'UPI / Card',
                'item_disc_amount' => 0,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'round_off' => 0,
                'total_gst' => 936.61,
                'total_cgst' => 468.30,
                'total_sgst' => 468.31,
                'total_igst' => 0,
                'total_extra_cess' => 0,
                'gst_calamity_cess' => 0,
                'total_qty' => 2,
                'total_weight' => 0,
                'total' => 6140.00,
                'remarks' => 'Online GPay/UPI Payment',
                'status' => 'Posted',
                'transport_mode' => 'Road',
                'vehicle_type' => 'Regular',
                'eway_status' => 'Not Generated',
                'einvoice_status' => 'Not Applicable',
                'posting_key' => (string) Str::uuid(),
            ]
        );

        // RC 1x @ 5800 (incl 18% GST: base 4915.25 + gst 884.75)
        SalesBillItem::firstOrCreate(
            ['sales_bill_id' => $sb2->id, 'item_id' => $rcItem->id],
            [
                'qty' => 1,
                'sell_price' => 5800.00,
                'cost_at_sale' => 4800.00,
                'mrp' => 6500.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18.00,
                'gst_tax_amount' => 884.75,
                'cgst_amount' => 442.37,
                'sgst_amount' => 442.38,
                'igst_amount' => 0,
                'net_amount' => 5800.00,
            ]
        );

        // Shampoo 1x @ 340 (incl 18% GST: base 288.14 + gst 51.86)
        SalesBillItem::firstOrCreate(
            ['sales_bill_id' => $sb2->id, 'item_id' => $hmItem->id],
            [
                'qty' => 1,
                'sell_price' => 340.00,
                'cost_at_sale' => 240.00,
                'mrp' => 380.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18.00,
                'gst_tax_amount' => 51.86,
                'cgst_amount' => 25.93,
                'sgst_amount' => 25.93,
                'igst_amount' => 0,
                'net_amount' => 340.00,
            ]
        );

        SalesBillPayment::firstOrCreate(
            ['sales_bill_id' => $sb2->id, 'tender_type_id' => $tenderUpi->id],
            ['amount' => 6140.00]
        );

        // Deduct stock for Sale 2 (-1 RC, -1 HM)
        $rcStock->decrement('quantity', 1);
        $hmStock = ItemStock::where('item_id', $hmItem->id)->where('branch_id', $branch->id)->first();
        $hmStock->decrement('quantity', 1);

        StockLedger::firstOrCreate(
            ['item_id' => $rcItem->id, 'reference_type' => 'SalesBill', 'reference_id' => $sb2->id],
            [
                'branch_id' => $branch->id,
                'movement_type' => 'SALE',
                'qty_in' => 0,
                'qty_out' => 1,
                'unit_cost' => 4800.00,
                'value_in' => 0,
                'value_out' => 4800.00,
                'running_balance_qty' => $rcStock->quantity,
                'running_balance_value' => $rcStock->quantity * 4800.00,
                'user_id' => $adminId,
                'document_date' => $sb2->bill_date,
                'posted_at' => now()->subDay(),
            ]
        );

        StockLedger::firstOrCreate(
            ['item_id' => $hmItem->id, 'reference_type' => 'SalesBill', 'reference_id' => $sb2->id],
            [
                'branch_id' => $branch->id,
                'movement_type' => 'SALE',
                'qty_in' => 0,
                'qty_out' => 1,
                'unit_cost' => 240.00,
                'value_in' => 0,
                'value_out' => 240.00,
                'running_balance_qty' => $hmStock->quantity,
                'running_balance_value' => $hmStock->quantity * 240.00,
                'user_id' => $adminId,
                'document_date' => $sb2->bill_date,
                'posted_at' => now()->subDay(),
            ]
        );

        // 6. Sales Return (1x Drools Chew Bone returned by Walk-in Customer)
        $sr = SalesReturn::firstOrCreate(
            ['return_number' => 'SR-2026-0001'],
            [
                'return_date' => now()->toDateString(),
                'customer_id' => $custWalk->id,
                'branch_id' => $branch->id,
                'sales_bill_id' => $sb1->id,
                'return_mode' => 'Cash',
                'sales_type' => 'Local',
                'item_disc_amount' => 0,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'round_off' => 0,
                'total_gst' => 5.71,
                'total_cgst' => 2.85,
                'total_sgst' => 2.86,
                'total_igst' => 0,
                'total_extra_cess' => 0,
                'gst_calamity_cess' => 0,
                'total' => 120.00,
                'remarks' => 'Customer bought wrong size bone, refund given',
                'status' => 'Posted',
                'posting_key' => (string) Str::uuid(),
            ]
        );

        SalesReturnItem::firstOrCreate(
            ['sales_return_id' => $sr->id, 'item_id' => $drItem->id],
            [
                'qty' => 1,
                'sell_price' => 120.00,
                'cost_at_sale' => 75.00,
                'mrp' => 150.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 5.00,
                'gst_tax_amount' => 5.71,
                'cgst_amount' => 2.85,
                'sgst_amount' => 2.86,
                'igst_amount' => 0,
                'net_amount' => 120.00,
            ]
        );

        // Restores stock (+1 DR)
        $drStock->increment('quantity', 1);

        StockLedger::firstOrCreate(
            ['item_id' => $drItem->id, 'reference_type' => 'SalesReturn', 'reference_id' => $sr->id],
            [
                'branch_id' => $branch->id,
                'movement_type' => 'SALE_RETURN',
                'qty_in' => 1,
                'qty_out' => 0,
                'unit_cost' => 75.00,
                'value_in' => 75.00,
                'value_out' => 0,
                'running_balance_qty' => $drStock->quantity,
                'running_balance_value' => $drStock->quantity * 75.00,
                'user_id' => $adminId,
                'document_date' => $sr->return_date,
                'posted_at' => now(),
            ]
        );

        // 7. Purchase Return (1x Pedigree 3kg returned to Mars Petcare due to package tear)
        $pr = PurchaseReturn::firstOrCreate(
            ['return_number' => 'PR-2026-0001'],
            [
                'return_date' => now()->toDateString(),
                'supplier_id' => $supMars->id,
                'branch_id' => $branch->id,
                'purchase_invoice_id' => $pi->id,
                'supplier_debit_note_no' => 'DN-2026-001',
                'supplier_debit_note_date' => now()->toDateString(),
                'purchase_type' => 'Local',
                'item_disc_amount' => 0,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'round_off' => 0,
                'total_gst' => 75.60,
                'total_cgst' => 37.80,
                'total_sgst' => 37.80,
                'total_igst' => 0,
                'total' => 495.60,
                'remarks' => 'Defective packaging, debit note issued',
                'status' => 'Posted',
                'posting_key' => (string) Str::uuid(),
            ]
        );

        PurchaseReturnItem::firstOrCreate(
            ['purchase_return_id' => $pr->id, 'item_id' => $pdItem->id],
            [
                'qty' => 1,
                'cost_price' => 420.00,
                'cost_at_return' => 420.00,
                'disc_percent' => 0,
                'disc_amount' => 0,
                'gst_percent' => 18.00,
                'gst_tax_amount' => 75.60,
                'cgst_amount' => 37.80,
                'sgst_amount' => 37.80,
                'igst_amount' => 0,
                'net_amount' => 495.60,
            ]
        );

        // Deduct stock for Purchase Return (-1 PD)
        $pdStock->decrement('quantity', 1);

        StockLedger::firstOrCreate(
            ['item_id' => $pdItem->id, 'reference_type' => 'PurchaseReturn', 'reference_id' => $pr->id],
            [
                'branch_id' => $branch->id,
                'movement_type' => 'PURCHASE_RETURN',
                'qty_in' => 0,
                'qty_out' => 1,
                'unit_cost' => 420.00,
                'value_in' => 0,
                'value_out' => 420.00,
                'running_balance_qty' => $pdStock->quantity,
                'running_balance_value' => $pdStock->quantity * 420.00,
                'user_id' => $adminId,
                'document_date' => $pr->return_date,
                'posted_at' => now(),
            ]
        );
    }
}
