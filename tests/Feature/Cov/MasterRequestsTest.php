<?php

namespace Tests\Feature\Cov;

use App\Http\Requests\Purchase\StorePurchaseInvoiceRequest;
use App\Http\Requests\Sales\StoreSalesBillRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * The FormRequest classes centralise header/line-item validation contracts.
 * These tests pin the contract: what is required, what is rejected.
 */
class MasterRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function validate($requestClass, array $data)
    {
        $req = new $requestClass();

        return Validator::make($data, $req->rules(), $req->messages());
    }

    private function salesPayload(array $over = []): array
    {
        $branch = Branch::create(['name' => 'RB']);
        $customer = Customer::create(['name' => 'RC', 'mobile' => '9000000055']);
        $item = Item::create(['name' => 'RI']);

        return array_merge([
            'bill_date' => now()->subMinute()->format('Y-m-d H:i:s'),
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'invoice_type' => 'Tax Invoice',
            'delivery_type' => 'Counter',
            'sales_type' => 'Local',
            'items' => [['item_id' => $item->id, 'qty' => 2, 'sell_price' => 10]],
        ], $over);
    }

    public function test_sales_bill_request_is_authorised_and_accepts_a_valid_payload(): void
    {
        $this->assertTrue((new StoreSalesBillRequest())->authorize());
        $this->assertTrue($this->validate(StoreSalesBillRequest::class, $this->salesPayload())->passes());
    }

    public function test_sales_bill_request_rejects_future_dates_with_custom_message(): void
    {
        $v = $this->validate(StoreSalesBillRequest::class, $this->salesPayload(['bill_date' => now()->addDays(2)->format('Y-m-d H:i:s')]));
        $this->assertTrue($v->fails());
        $this->assertSame('Future date and time is not allowed for Bill Date.', $v->errors()->first('bill_date'));
    }

    public function test_sales_bill_request_enforces_enums_required_and_line_rules(): void
    {
        $p = $this->salesPayload();
        $v = $this->validate(StoreSalesBillRequest::class, array_merge($p, [
            'invoice_type' => 'Proforma', 'sales_type' => 'Abroad', 'customer_id' => 99999, 'delivery_time' => '25:99',
            'items' => [['item_id' => $p['items'][0]['item_id'], 'qty' => 0, 'sell_price' => -1]],
        ]));
        $errs = $v->errors()->keys();
        foreach (['invoice_type', 'sales_type', 'customer_id', 'delivery_time', 'items.0.qty', 'items.0.sell_price'] as $k) {
            $this->assertContains($k, $errs, "expected error on $k");
        }

        $empty = $this->validate(StoreSalesBillRequest::class, array_merge($p, ['items' => []]));
        $this->assertContains('items', $empty->errors()->keys());
    }

    public function test_sales_bill_request_payment_lines_need_tender_and_positive_amount(): void
    {
        $v = $this->validate(StoreSalesBillRequest::class, $this->salesPayload(['payments' => [['amount' => 0]]]));
        $this->assertContains('payments.0.tender_type_id', $v->errors()->keys());
        $this->assertContains('payments.0.amount', $v->errors()->keys());
    }

    public function test_purchase_invoice_request_accepts_valid_and_rejects_bad_payloads(): void
    {
        $this->assertTrue((new StorePurchaseInvoiceRequest())->authorize());

        $supplier = Supplier::create(['name' => 'RS', 'currency' => 'INR', 'purchase_type' => 'Local', 'purchase_mode' => 'Credit', 'credit_limit' => 0, 'credit_balance' => 0, 'credit_days' => 0, 'status' => 1, 'gst_type' => 'Regular', 'mail_type' => 'None']);
        $branch = Branch::create(['name' => 'PB']);
        $item = Item::create(['name' => 'PI']);
        $good = [
            'header' => ['invoice_date' => '2026-04-10', 'supplier_id' => $supplier->id, 'branch_id' => $branch->id, 'purchase_type' => 'Local'],
            'items' => [['item_id' => $item->id, 'qty' => 1, 'cost_price' => 5]],
        ];
        $this->assertTrue($this->validate(StorePurchaseInvoiceRequest::class, $good)->passes());

        $bad = $this->validate(StorePurchaseInvoiceRequest::class, [
            'header' => ['supplier_id' => 99999, 'purchase_type' => 'Moon', 'freight' => -5],
            'items' => [],
        ]);
        $keys = $bad->errors()->keys();
        foreach (['header.invoice_date', 'header.supplier_id', 'header.branch_id', 'header.purchase_type', 'header.freight', 'items'] as $k) {
            $this->assertContains($k, $keys, "expected error on $k");
        }
        $this->assertSame('Invoice date is required.', $bad->errors()->first('header.invoice_date'));
        $this->assertSame('At least one line item is required.', $bad->errors()->first('items'));

        $line = $this->validate(StorePurchaseInvoiceRequest::class, array_replace_recursive($good, ['items' => [['qty' => 0, 'cost_price' => -1]]]));
        $this->assertContains('items.0.qty', $line->errors()->keys());
        $this->assertContains('items.0.cost_price', $line->errors()->keys());
    }
}
