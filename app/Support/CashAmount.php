<?php

namespace App\Support;

use InvalidArgumentException;

/** Exact arithmetic for the existing DECIMAL(12,2) columns; never uses floats. */
final class CashAmount
{
    public const MAX_CENTS = 999999999999;

    public static function cents(string $amount): int
    {
        if (! preg_match('/\A(-?)(\d{1,10})(?:\.(\d{1,2}))?\z/', $amount, $parts)) {
            throw new InvalidArgumentException('Importe fuera de formato o rango.');
        }

        $cents = ((int) $parts[2] * 100) + (int) str_pad($parts[3] ?? '', 2, '0');

        return $parts[1] === '-' ? -$cents : $cents;
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function format(string $amount): string
    {
        [$whole, $fraction] = explode('.', self::decimal(self::cents($amount)));

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole).'.'.$fraction;
    }
}
