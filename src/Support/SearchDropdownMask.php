<?php

declare(strict_types=1);

namespace Ptah\Support;

use Carbon\Carbon;

/**
 * Shared built-in format masks for search-dropdown labels.
 *
 * Extracted from `Ptah\Livewire\SearchDropdown\SearchDropdown::formatValue()`
 * (the built-in branch only — cnpj/cpf/money/phone/date) so the BaseCrud
 * inline widget (`HasCrudSearchDropdown`) can apply the exact same
 * transformations without depending on the Livewire component.
 *
 * Deliberately excludes the dynamic branches of the original
 * (`Class::method`, `Class@method`, local component method): letting an
 * editable config execute an arbitrary class/method is a code-execution
 * vector, and this helper is reachable from config-driven column definitions.
 * Those branches remain the exclusive responsibility of
 * SearchDropdown::formatValue(), which still resolves them itself before
 * ever consulting the builtins here.
 */
final class SearchDropdownMask
{
    /**
     * @return list<string> names of the built-in masks this helper knows.
     */
    public static function builtins(): array
    {
        return ['cnpj', 'cpf', 'money', 'phone', 'date'];
    }

    /**
     * Formats $value using $mask.
     *
     * Resolution:
     *   1. 'defaultMask' or an unknown mask name → value as-is
     *   2. a built-in name (see builtins()) → the corresponding transform
     */
    public static function format(mixed $value, string $mask): string
    {
        if ($value === null) {
            return '';
        }

        $v = (string) $value;

        return match ($mask) {
            'cnpj' => self::cnpj($v),
            'cpf' => self::cpf($v),
            'money' => self::money($v),
            'phone' => self::phone($v),
            'date' => self::date($v),
            default => $v,
        };
    }

    private static function cnpj(string $v): string
    {
        $digits = preg_replace('/\D/', '', $v);
        if (strlen($digits) !== 14) {
            return $v;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2)
        );
    }

    private static function cpf(string $v): string
    {
        $digits = preg_replace('/\D/', '', $v);
        if (strlen($digits) !== 11) {
            return $v;
        }

        return sprintf(
            '%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2)
        );
    }

    private static function money(string $v): string
    {
        $num = (float) str_replace(',', '.', preg_replace('/[^\d,.]/', '', $v));

        return 'R$ '.number_format($num, 2, ',', '.');
    }

    private static function phone(string $v): string
    {
        $digits = preg_replace('/\D/', '', $v);
        $len = strlen($digits);

        if ($len === 11) {
            return sprintf('(%s) %s %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 1),
                substr($digits, 3, 4),
                substr($digits, 7, 4)
            );
        }

        if ($len === 10) {
            return sprintf('(%s) %s-%s',
                substr($digits, 0, 2),
                substr($digits, 2, 4),
                substr($digits, 6, 4)
            );
        }

        return $v;
    }

    private static function date(string $v): string
    {
        try {
            return Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable) {
            return $v;
        }
    }
}
