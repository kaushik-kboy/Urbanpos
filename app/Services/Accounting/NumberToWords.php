<?php

namespace App\Services\Accounting;

class NumberToWords
{
    private static array $units = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
    ];

    private static array $tens = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
    ];

    /**
     * Convert numeric amount to Indian currency in words string.
     * Example: 9126.00 -> "Rupees Nine Thousand One Hundred Twenty Six Only"
     */
    public static function toIndianCurrency(float|int|string $number): string
    {
        $number = (float) $number;
        if ($number <= 0) {
            return 'Rupees Zero Only';
        }

        $rupees = (int) floor($number);
        $paise = (int) round(($number - $rupees) * 100);

        $rupeesWords = self::convertToIndianWords($rupees);
        $result = 'Rupees ' . trim($rupeesWords);

        if ($paise > 0) {
            $paiseWords = self::convertToIndianWords($paise);
            $result .= ' and ' . trim($paiseWords) . ' Paise';
        }

        return $result . ' Only';
    }

    private static function convertToIndianWords(int $num): string
    {
        if ($num === 0) {
            return '';
        }

        if ($num < 20) {
            return self::$units[$num];
        }

        if ($num < 100) {
            $ten = (int) ($num / 10);
            $unit = $num % 10;
            return self::$tens[$ten] . ($unit > 0 ? ' ' . self::$units[$unit] : '');
        }

        if ($num < 1000) {
            $hundred = (int) ($num / 100);
            $rem = $num % 100;
            return self::$units[$hundred] . ' Hundred' . ($rem > 0 ? ' ' . self::convertToIndianWords($rem) : '');
        }

        if ($num < 100000) { // Thousands (up to 99,999)
            $thousand = (int) ($num / 1000);
            $rem = $num % 1000;
            return self::convertToIndianWords($thousand) . ' Thousand' . ($rem > 0 ? ' ' . self::convertToIndianWords($rem) : '');
        }

        if ($num < 10000000) { // Lakhs (up to 99,99,999)
            $lakh = (int) ($num / 100000);
            $rem = $num % 100000;
            return self::convertToIndianWords($lakh) . ' Lakh' . ($rem > 0 ? ' ' . self::convertToIndianWords($rem) : '');
        }

        // Crores
        $crore = (int) ($num / 10000000);
        $rem = $num % 10000000;
        return self::convertToIndianWords($crore) . ' Crore' . ($rem > 0 ? ' ' . self::convertToIndianWords($rem) : '');
    }
}
