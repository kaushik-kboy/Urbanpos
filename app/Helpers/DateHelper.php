<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Normalize any raw user date input into standard MySQL 'YYYY-MM-DD'.
     *
     * Handles:
     * - DDMMYYYY (e.g. 10042026 -> 2026-04-10)
     * - DD-MM-YYYY or DD/MM/YYYY (e.g. 10-04-2026 -> 2026-04-10)
     * - YYYY-MM-DD (e.g. 2026-04-10 -> 2026-04-10)
     * - DDMMYY (e.g. 100426 -> 2026-04-10)
     * - DDMM (e.g. 1004 -> 2026-04-10 for current year)
     * - YYYYMMDD (e.g. 20260410 -> 2026-04-10)
     */
    public static function normalize(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $str = trim((string) $raw);
        if ($str === '') {
            return null;
        }

        // 1. Already valid YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            try {
                $c = Carbon::createFromFormat('Y-m-d', $str);
                return ($c && $c->format('Y-m-d') === $str) ? $str : null;
            } catch (\Exception $e) {
                return null;
            }
        }

        // 2. Separated by -, /, or .
        $parts = preg_split('/[\/\-\.]/', $str);
        if (count($parts) === 3) {
            if (strlen($parts[0]) === 4) {
                // YYYY-MM-DD or YYYY/MM/DD
                $y = (int) $parts[0];
                $m = (int) $parts[1];
                $d = (int) $parts[2];
            } else {
                // DD-MM-YYYY or DD/MM/YYYY (Indian standard)
                $d = (int) $parts[0];
                $m = (int) $parts[1];
                $yStr = $parts[2];
                $y = strlen($yStr) === 2 ? 2000 + (int) $yStr : (int) $yStr;
            }
            if ($y >= 1900 && $y <= 2100 && checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        } elseif (count($parts) === 2) {
            // DD-MM (current year)
            $d = (int) $parts[0];
            $m = (int) $parts[1];
            $y = (int) date('Y');
            if (checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }

        // 3. Pure digits without separators
        $digits = preg_replace('/\D/', '', $str);
        if (strlen($digits) === 8) {
            // Check if starts with 4-digit year (YYYYMMDD)
            $first4 = (int) substr($digits, 0, 4);
            if ($first4 >= 1900 && $first4 <= 2100) {
                $y = $first4;
                $m = (int) substr($digits, 4, 2);
                $d = (int) substr($digits, 6, 2);
                if (checkdate($m, $d, $y)) {
                    return sprintf('%04d-%02d-%02d', $y, $m, $d);
                }
            }

            // Standard Indian DDMMYYYY (e.g. 10042026)
            $d = (int) substr($digits, 0, 2);
            $m = (int) substr($digits, 2, 2);
            $y = (int) substr($digits, 4, 4);
            if ($y >= 1900 && $y <= 2100 && checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        } elseif (strlen($digits) === 6) {
            // DDMMYY (e.g. 100426)
            $d = (int) substr($digits, 0, 2);
            $m = (int) substr($digits, 2, 2);
            $y = 2000 + (int) substr($digits, 4, 2);
            if (checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        } elseif (strlen($digits) === 4) {
            // DDMM (e.g. 1004 -> current year)
            $d = (int) substr($digits, 0, 2);
            $m = (int) substr($digits, 2, 2);
            $y = (int) date('Y');
            if (checkdate($m, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }

        // Fallback with Carbon
        try {
            $parsed = Carbon::parse($str);
            return $parsed ? $parsed->format('Y-m-d') : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Format a date string from DB (Y-m-d) to display format.
     */
    public static function format(?string $date, string $format = 'd-m-Y'): string
    {
        if (empty($date)) {
            return '';
        }
        try {
            return Carbon::parse($date)->format($format);
        } catch (\Exception $e) {
            return (string) $date;
        }
    }
}
