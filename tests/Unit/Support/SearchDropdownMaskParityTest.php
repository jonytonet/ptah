<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\SearchDropdown\SearchDropdown;
use Ptah\Support\SearchDropdownMask;
use Ptah\Tests\TestCase;

/**
 * Pins SearchDropdownMask::format() as byte-identical to
 * SearchDropdown::formatValue() for every built-in mask (cnpj, cpf, money,
 * phone, date) plus the "unknown mask" fallback — the shared helper was
 * extracted straight out of formatValue()'s built-in branch and must never
 * silently drift from it.
 */
class SearchDropdownMaskParityTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function maskedValues(): array
    {
        return [
            'cnpj valid' => ['cnpj', '11222333000181'],
            'cnpj invalid length' => ['cnpj', '123'],
            'cpf valid' => ['cpf', '12345678909'],
            'cpf invalid length' => ['cpf', '123'],
            'money plain' => ['money', '1234.5'],
            'money brl-ish' => ['money', '1.234,56'],
            'phone mobile (11)' => ['phone', '11999998888'],
            'phone landline (10)' => ['phone', '1133334444'],
            'phone invalid length' => ['phone', '123'],
            'date iso' => ['date', '2024-01-15'],
            'date invalid' => ['date', 'not-a-date'],
            'unknown mask (cru)' => ['totally-unknown-mask', 'raw-value'],
            'defaultMask (cru)' => ['defaultMask', 'raw-value'],
            // Achado de revisao: rotulos reais podem ser '' ou lixo — paridade vale igual.
            'money empty string' => ['money', ''],
            'money garbage' => ['money', 'abc'],
            'cnpj empty string' => ['cnpj', ''],
            'phone garbage' => ['phone', '12x34'],
            'date empty string' => ['date', ''],
        ];
    }

    #[Test]
    #[DataProvider('maskedValues')]
    public function helper_matches_the_component_formatvalue(string $mask, string $value): void
    {
        $component = Livewire::test(SearchDropdown::class, ['model' => 'Widget']);

        $viaComponent = $component->instance()->formatValue($value, $mask);
        $viaHelper = SearchDropdownMask::format($value, $mask);

        $this->assertSame($viaComponent, $viaHelper);
    }

    #[Test]
    public function builtins_list_matches_the_known_mask_names(): void
    {
        $this->assertSame(['cnpj', 'cpf', 'money', 'phone', 'date'], SearchDropdownMask::builtins());
    }

    #[Test]
    public function null_value_formats_to_an_empty_string(): void
    {
        $this->assertSame('', SearchDropdownMask::format(null, 'cnpj'));
    }
}
