<?php

namespace App\Support;

use Carbon\Carbon;
use JsonSerializable;
use Stringable;

class PetBirthDate implements Stringable, JsonSerializable
{
    protected string $value;

    public function __construct($value)
    {
        $val = trim((string) $value);

        // Normalize slashes and dots to hyphens
        $val = str_replace(['/', '.'], '-', $val);

        // If ISO timestamp (e.g. 2024-05-15T00:00:00.000000Z) or YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $val, $m)) {
            $val = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        $this->value = $val;
    }

    /**
     * Format the birth date according to standard date formats or preserve MM-YYYY
     */
    public function format(string $format = 'd-m-Y'): string
    {
        // Month and Year only (MM-YYYY)
        if (preg_match('/^(\d{1,2})-(\d{4})$/', $this->value, $m)) {
            $month = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $year = $m[2];

            // If day format characters are requested, return MM-YYYY because day was not specified
            if (str_contains($format, 'd') || str_contains($format, 'j')) {
                return "{$month}-{$year}";
            }

            try {
                return Carbon::createFromDate((int)$year, (int)$month, 1)->format($format);
            } catch (\Throwable) {
                return "{$month}-{$year}";
            }
        }

        // Full Date (DD-MM-YYYY)
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $this->value, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];

            try {
                return Carbon::createFromDate($year, $month, $day)->format($format);
            } catch (\Throwable) {
                return $this->value;
            }
        }

        try {
            return Carbon::parse($this->value)->format($format);
        } catch (\Throwable) {
            return $this->value;
        }
    }

    /**
     * Convert to standard YYYY-MM-DD date string (using 1st of month if only MM-YYYY provided)
     */
    public function toDateString(): string
    {
        if (preg_match('/^(\d{1,2})-(\d{4})$/', $this->value, $m)) {
            return Carbon::createFromDate((int) $m[2], (int) $m[1], 1)->toDateString();
        }

        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $this->value, $m)) {
            return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
        }

        try {
            return Carbon::parse($this->value)->toDateString();
        } catch (\Throwable) {
            return $this->value;
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
