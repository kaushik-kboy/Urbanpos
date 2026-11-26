<?php

namespace Tests\Unit;

use App\Http\Controllers\Master\BarcodePrintController;
use Tests\TestCase;

class TsplBarcodePrintTest extends TestCase
{
    private BarcodePrintController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new BarcodePrintController();
    }

    public function test_tspl_generates_correct_horizontal_header_for_2up(): void
    {
        $labels = [
            [
                'name' => 'Apro Cat 20kg',
                'barcode' => '8850477013575',
                'mrp' => 3100.0,
                'sell_price' => 3100.0,
            ]
        ];

        $tspl = $this->controller->generateTspl($labels, 'URBAN POS', '50x38_2up');

        $this->assertStringContainsString('SIZE 102 mm, 38 mm', $tspl);
        $this->assertStringContainsString('DIRECTION 1', $tspl);
        $this->assertStringContainsString('PRINT 1,1', $tspl);
    }

    public function test_tspl_auto_pairs_single_label_into_two_stickers(): void
    {
        $labels = [
            [
                'name' => 'Apro Cat 20kg',
                'barcode' => '8850477013575',
                'mrp' => 3100.0,
                'sell_price' => 3100.0,
            ]
        ];

        $tspl = $this->controller->generateTspl($labels, 'URBAN POS', '50x38_2up');

        // Left sticker at X=359, Right sticker at X=699 (both rotated 90)
        $this->assertStringContainsString('TEXT 359,15,"3",90,1,1,"URBAN POS"', $tspl);
        $this->assertStringContainsString('TEXT 699,15,"3",90,1,1,"URBAN POS"', $tspl);
        $this->assertStringContainsString('BARCODE 284,15,"128",75,1,90,3,3,"8850477013575"', $tspl);
        $this->assertStringContainsString('BARCODE 624,15,"128",75,1,90,3,3,"8850477013575"', $tspl);
    }

    public function test_tspl_short_name_uses_single_line_font_3(): void
    {
        $labels = [
            [
                'name' => 'Apro Cat 20kg',
                'barcode' => '8850477013575',
                'mrp' => 3100.0,
                'sell_price' => 3100.0,
            ]
        ];

        $tspl = $this->controller->generateTspl($labels, 'URBAN POS', '50x38_2up');

        $this->assertStringContainsString('TEXT 324,15,"3",90,1,1,"Apro Cat 20kg"', $tspl);
        $this->assertStringContainsString('TEXT 664,15,"3",90,1,1,"Apro Cat 20kg"', $tspl);
    }

    public function test_tspl_long_name_auto_wraps_into_font_2(): void
    {
        $labels = [
            [
                'name' => 'Royal Canin Maxi Adult Dog Food 15kg',
                'barcode' => '8850477013575',
                'mrp' => 3100.0,
                'sell_price' => 3100.0,
            ]
        ];

        $tspl = $this->controller->generateTspl($labels, 'URBAN POS', '50x38_2up');

        // Uses Font "2" at X=324/664
        $this->assertStringContainsString('TEXT 324,15,"2",90,1,1,"Royal Canin Maxi Adult Dog"', $tspl);
        $this->assertStringContainsString('TEXT 664,15,"2",90,1,1,"Royal Canin Maxi Adult Dog"', $tspl);
    }

    public function test_tspl_prices_format_integers_without_trailing_decimals(): void
    {
        $labels = [
            [
                'name' => 'Apro Cat 20kg',
                'barcode' => '8850477013575',
                'mrp' => 3100.0,
                'sell_price' => 3100.0,
            ]
        ];

        $tspl = $this->controller->generateTspl($labels, 'URBAN POS', '50x38_2up');

        $this->assertStringContainsString('MRP : Rs. 3,100', $tspl);
        $this->assertStringNotContainsString('MRP : Rs. 3,100.00', $tspl);
        $this->assertStringContainsString('(Incl. TAX)', $tspl);
    }

    public function test_tspl_empty_labels_returns_empty_string(): void
    {
        $tspl = $this->controller->generateTspl([], 'URBAN POS', '50x38_2up');
        $this->assertSame('', $tspl);
    }

    public function test_print_labels_view_contains_embedded_tspl_for_post(): void
    {
        $request = \Illuminate\Http\Request::create('/inventory/barcode/print', 'POST', [
            'items' => [
                [
                    'id' => 1,
                    'name' => 'Test Collar',
                    'code' => '5240',
                    'barcode' => '5240',
                    'mrp' => 80,
                    'sell_price' => 80,
                    'qty' => 2,
                ]
            ]
        ]);

        $response = $this->controller->printLabels($request);
        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $data = $response->getData();
        $this->assertArrayHasKey('tspl', $data);
        $this->assertStringContainsString('PRINT 1,1', $data['tspl']);
        $this->assertStringContainsString('BARCODE', $data['tspl']);
        $this->assertStringContainsString('Test Collar', $data['tspl']);
    }
}
