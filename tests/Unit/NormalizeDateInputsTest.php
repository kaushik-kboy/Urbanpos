<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Http\Middleware\NormalizeDateInputs;

class NormalizeDateInputsTest extends TestCase
{
    public function test_normalizes_top_level_date_inputs()
    {
        $middleware = new NormalizeDateInputs();

        $request = Request::create('/test', 'POST', [
            'invoice_date' => '10042026',
            'order_date' => '10-04-2026',
            'delivery_date' => '10/04/2026',
            'supplier_name' => 'Acme Corp',
            'total' => '1500.50',
            'from' => '01-01-2026',
            'to' => '31-12-2026',
        ]);

        $response = $middleware->handle($request, function ($req) {
            return response()->json($req->all());
        });

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('2026-04-10', $data['invoice_date']);
        $this->assertEquals('2026-04-10', $data['order_date']);
        $this->assertEquals('2026-04-10', $data['delivery_date']);
        $this->assertEquals('Acme Corp', $data['supplier_name']);
        $this->assertEquals('1500.50', $data['total']);
        $this->assertEquals('2026-01-01', $data['from']);
        $this->assertEquals('2026-12-31', $data['to']);
    }

    public function test_normalizes_nested_date_inputs_in_items()
    {
        $middleware = new NormalizeDateInputs();

        $request = Request::create('/test', 'POST', [
            'invoice_date' => '24-09-2026',
            'items' => [
                [
                    'item_id' => 1,
                    'exp_date' => '10042026',
                    'qty' => 5,
                ],
                [
                    'item_id' => 2,
                    'exp_date' => '15-08-2027',
                    'qty' => 10,
                ],
            ],
        ]);

        $response = $middleware->handle($request, function ($req) {
            return response()->json($req->all());
        });

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('2026-09-24', $data['invoice_date']);
        $this->assertEquals('2026-04-10', $data['items'][0]['exp_date']);
        $this->assertEquals('2027-08-15', $data['items'][1]['exp_date']);
        $this->assertEquals(5, $data['items'][0]['qty']);
    }

    public function test_leaves_non_date_keys_untouched()
    {
        $middleware = new NormalizeDateInputs();

        $request = Request::create('/test', 'POST', [
            'candidate_id' => '12345',
            'consolidated' => 'yes',
            'update_rate' => '100',
        ]);

        $response = $middleware->handle($request, function ($req) {
            return response()->json($req->all());
        });

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('12345', $data['candidate_id']);
        $this->assertEquals('yes', $data['consolidated']);
        $this->assertEquals('100', $data['update_rate']);
    }
}
