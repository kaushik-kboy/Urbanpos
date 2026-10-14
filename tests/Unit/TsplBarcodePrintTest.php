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

        // Left sticker at X=115, Right sticker at X=430
        $this->assertStringContainsString('TEXT 115,15,"3",0,1,1,"URBAN POS"', $tspl);
        $this->assertStringContainsString('TEXT 430,15,"3",0,1,1,"URBAN POS"', $tspl);
        $this->assertStringContainsString('BARCODE 115,88,"128",55,1,0,2,2,"8850477013575"', $tspl);
        $this->assertStringContainsString('BARCODE 430,88,"128",55,1,0,2,2,"8850477013575"', $tspl);
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

        $this->assertStringContainsString('TEXT 115,48,"3",0,1,1,"Apro Cat 20kg"', $tspl);
        $this->assertStringContainsString('TEXT 430,48,"3",0,1,1,"Apro Cat 20kg"', $tspl);
    }

    public function test_tspl_long_name_auto_wraps_into_two_lines_font_2(): void
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

        // Line 1 at Y=38, Line 2 at Y=60 using Font "2"
        $this->assertStringContainsString('TEXT 115,38,"2",0,1,1,"Royal Canin Maxi"', $tspl);
        $this->assertStringContainsString('TEXT 115,60,"2",0,1,1,"Adult Dog Food 15kg"', $tspl);
        $this->assertStringContainsString('TEXT 430,38,"2",0,1,1,"Royal Canin Maxi"', $tspl);
        $this->assertStringContainsString('TEXT 430,60,"2",0,1,1,"Adult Dog Food 15kg"', $tspl);
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

        $this->assertStringContainsString('MRP: Rs. 3,100', $tspl);
        $this->assertStringNotContainsString('MRP: Rs. 3,100.00', $tspl);
        $this->assertStringContainsString('(Incl. TAX)', $tspl);
    }
}
