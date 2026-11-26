<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Reads a money amount typed by a person — "R$ 2.160,00", "2160.00", "1,50"
 * — into integer cents (1.62.0).
 *
 * A filter on a column kept in cents (colsRenderer money + colsRendererCents)
 * compared what was typed straight with the stored integer: typing 2160 looked
 * for 2160 cents (R$ 21,60), and "2.160,00" did not even become a number.
 *
 * Rules, for pt-BR without breaking whoever types with a dot:
 * 1. "R$", spaces and the column's currency symbol go.
 * 2. Comma AND dot: the one that comes last is the decimal mark.
 * 3. Only commas: the last one is the decimal mark ("1,50", "2160,5").
 * 4. Only dots: 1 or 2 digits after the last one make it decimal ("1.50");
 *    groups of 3 make them thousands ("2.160", "1.234.567").
 * 5. No separator: whole units ("150" = 150,00).
 * The cents are computed on the string, not on a float, so 0,29 is 29.
 */
final class MoneyInput
{
    /** Integer cents, or null when what was typed is not an amount. */
    public static function toCents(mixed $raw, string $currency = ''): ?int
    {
        if (is_int($raw)) {
            return $raw * 100;
        }
        if (is_float($raw)) {
            $raw = number_format($raw, 2, '.', '');
        }
        if (! is_string($raw)) {
            return null;
        }

        $s = str_replace(["\u{00A0}", ' ', 'R$', 'r$'], '', $raw);
        if ($currency !== '') {
            $s = str_replace($currency, '', $s);
        }
        $s = trim($s);

        $negative = false;
        if (str_starts_with($s, '-')) {
            $negative = true;
            $s = substr($s, 1);
        }

        if ($s === '' || preg_match('/^[0-9.,]+$/', $s) !== 1 || preg_match('/[0-9]/', $s) !== 1) {
            return null;
        }

        $comma = strrpos($s, ',');
        $dot = strrpos($s, '.');

        if ($comma !== false && $dot !== false) {
            // The separator that comes last is the decimal one.
            $decimalAt = max($comma, $dot);
        } elseif ($comma !== false) {
            $decimalAt = $comma;
        } elseif ($dot !== false) {
            $tail = strlen($s) - $dot - 1;
            $decimalAt = ($tail >= 1 && $tail <= 2) ? $dot : null;
        } else {
            $decimalAt = null;
        }

        if ($decimalAt === null) {
            $int = str_replace(['.', ','], '', $s);
            $frac = '';
        } else {
            $int = str_replace(['.', ','], '', substr($s, 0, $decimalAt));
            $frac = substr($s, $decimalAt + 1);
            if ($frac === '' || preg_match('/^[0-9]+$/', $frac) !== 1) {
                return null;
            }
        }

        if ($int === '') {
            $int = '0';
        }
        if (preg_match('/^[0-9]+$/', $int) !== 1 || strlen($int) > 15) {
            return null;
        }

        // Two decimals, rounding half up on the third.
        $frac = str_pad($frac, 3, '0');
        $cents = (int) $int * 100 + (int) substr($frac, 0, 2) + ((int) $frac[2] >= 5 ? 1 : 0);

        return $negative ? -$cents : $cents;
    }

    /** "2160.00" — how a stored cents value is written back into a filter. */
    public static function fromCents(int|string $cents): string
    {
        $c = (int) $cents;
        $sign = $c < 0 ? '-' : '';
        $c = abs($c);

        return $sign.intdiv($c, 100).'.'.str_pad((string) ($c % 100), 2, '0', STR_PAD_LEFT);
    }
}
