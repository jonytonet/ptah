<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudForm;
use Ptah\Tests\TestCase;

// ── Harness exposing the protected transform method ──────────────────────────

class MaskHarness
{
    use HasCrudForm;

    public array $crudConfig = [];

    public string $model = 'Test';

    public function transform(array $data, array $formCols): array
    {
        return $this->applyMaskTransforms($data, $formCols);
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Covers applyMaskTransforms — the form-to-database value conversions
 * (money, documents, plates, dates, case). These run on every save with a
 * colsMaskTransform configured, so silent regressions corrupt data.
 */
class CrudMaskTransformsTest extends TestCase
{
    private function transform(string $mask, mixed $value): mixed
    {
        $result = (new MaskHarness)->transform(
            ['field' => $value],
            [['colsNomeFisico' => 'field', 'colsMaskTransform' => $mask]],
        );

        return $result['field'];
    }

    // ── money_to_float ────────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('moneyCases')]
    public function money_to_float_handles_brazilian_and_english_formats(string $input, float $expected): void
    {
        $this->assertSame($expected, $this->transform('money_to_float', $input));
    }

    public static function moneyCases(): array
    {
        return [
            'BR currency' => ['R$ 1.253,08', 1253.08],
            'BR thousands' => ['1.234,56', 1234.56],
            'EN thousands' => ['1,234.56', 1234.56],
            'comma decimal' => ['25,50', 25.5],
            'comma thousands (3 digits)' => ['1,000', 1000.0],
            'plain float' => ['25.5', 25.5],
            'integer' => ['255', 255.0],
            'empty' => ['', 0.0],
        ];
    }

    // ── Documents / plates ────────────────────────────────────────────────────

    #[Test]
    public function digits_only_strips_document_punctuation(): void
    {
        $this->assertSame('05546530952', $this->transform('digits_only', '055.465.309-52'));
    }

    #[Test]
    public function plate_clean_uppercases_and_strips_separators(): void
    {
        $this->assertSame('ABC1D23', $this->transform('plate_clean', 'abc-1d23'));
    }

    // ── Dates ─────────────────────────────────────────────────────────────────

    #[Test]
    public function date_br_to_iso_converts_valid_dates_and_keeps_invalid_input(): void
    {
        $this->assertSame('2024-12-01', $this->transform('date_br_to_iso', '01/12/2024'));
        // Invalid input is returned untouched (validation reports it, transform must not corrupt).
        $this->assertSame('not-a-date', $this->transform('date_br_to_iso', 'not-a-date'));
    }

    #[Test]
    public function date_iso_to_br_converts_back(): void
    {
        $this->assertSame('01/12/2024', $this->transform('date_iso_to_br', '2024-12-01'));
    }

    // ── Case / trim / unknown ─────────────────────────────────────────────────

    #[Test]
    public function case_and_trim_transforms_work(): void
    {
        $this->assertSame('HELLO', $this->transform('uppercase', 'hello'));
        $this->assertSame('hello', $this->transform('lowercase', 'HELLO'));
        $this->assertSame('hello', $this->transform('trim', '  hello  '));
    }

    #[Test]
    public function unknown_transform_leaves_the_value_untouched(): void
    {
        $this->assertSame('raw', $this->transform('does_not_exist', 'raw'));
    }

    #[Test]
    public function fields_without_transform_or_absent_from_data_are_skipped(): void
    {
        $harness = new MaskHarness;

        $result = $harness->transform(
            ['a' => ' keep ', 'c' => 'x'],
            [
                ['colsNomeFisico' => 'a'],                                        // no transform
                ['colsNomeFisico' => 'b', 'colsMaskTransform' => 'uppercase'],    // not in data
                ['colsNomeFisico' => 'c', 'colsMaskTransform' => 'uppercase'],
            ],
        );

        $this->assertSame(' keep ', $result['a']);
        $this->assertArrayNotHasKey('b', $result);
        $this->assertSame('X', $result['c']);
    }
}
