<?php

namespace App\Modules\Produksi\Services;

class WipFormatter
{
    public static function prettyDate(?string $value): string
    {
        if (!$value) {
            return '—';
        }
        $slice = substr(trim($value), 0, 10);
        $parts = explode('-', $slice);
        if (count($parts) === 3 && strlen($parts[0]) === 4) {
            return "{$parts[2]}/{$parts[1]}/{$parts[0]}";
        }
        return $value;
    }

    public static function timeDisplay(?string $value): string
    {
        if (!$value) {
            return '—';
        }
        return substr(trim($value), 0, 5);
    }

    public static function dateTimeDisplay(?string $date, ?string $time): string
    {
        if (!$date && !$time) {
            return '—';
        }
        $d = $date ? self::prettyDate($date) : '—';
        $t = $time ? ' · ' . self::timeDisplay($time) : '';
        return $d . $t;
    }

    public static function number(mixed $value, int $digits = 2): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return '—';
        }
        $numericValue = (float) $value;
        if (!is_finite($numericValue)) {
            return '—';
        }

        $maxDigits = min(2, max(0, $digits));
        $formatted = number_format($numericValue, $maxDigits, ',', '.');

        if ($maxDigits > 0 && str_contains($formatted, ',')) {
            $formatted = rtrim(rtrim($formatted, '0'), ',');
        }

        return $formatted === '-0' ? '0' : $formatted;
    }
}
