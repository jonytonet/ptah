<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Exports;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Exports\CrudExport;
use ReflectionClass;

/**
 * Guards against formula/CSV injection in Excel exports (OWASP: a cell value
 * that starts with `=` becomes TYPE_FORMULA when PhpSpreadsheet writes the
 * .xlsx (DefaultValueBinder only reinterprets `=`), letting a malicious record
 * value exfiltrate data via e.g. `=HYPERLINK(...)`.
 *
 * `formatValue()` must prefix such values with a leading apostrophe. The guard
 * deliberately covers ONLY `=`: in a programmatically written .xlsx the
 * apostrophe stays literal and visible in the cell, so prefixing `+`/`-`/`@`
 * (which PhpSpreadsheet already stores as TYPE_STRING) would corrupt phones
 * ("+55 11 ...") and handles ("@user") for zero security gain.
 */
class CrudExportFormulaInjectionTest extends TestCase
{
    #[Test]
    #[DataProvider('dangerousStrings')]
    public function it_neutralizes_strings_that_look_like_formulas(string $value): void
    {
        $this->assertSame("'".$value, $this->formatValue($value));
    }

    #[Test]
    #[DataProvider('safeValues')]
    public function it_leaves_safe_values_untouched(mixed $value, mixed $expected): void
    {
        $this->assertSame($expected, $this->formatValue($value));
    }

    public static function dangerousStrings(): array
    {
        return [
            'formula (=HYPERLINK)' => ['=HYPERLINK("http://evil.test","click")'],
            'formula (=cmd)' => ['=cmd|\' /C calc\'!A0'],
            'formula (=1+1)' => ['=1+1'],
        ];
    }

    public static function safeValues(): array
    {
        return [
            'negative float stays intact' => ['-15.3', '-15.3'],
            'negative int stays intact' => ['-42', '-42'],
            'positive numeric string stays intact' => ['42', '42'],
            'plain text stays intact' => ['John Doe', 'John Doe'],
            'null becomes empty string' => [null, ''],
            'formatted phone with + stays intact' => ['+55 11 99999-8888', '+55 11 99999-8888'],
            'handle with @ stays intact' => ['@joao.silva', '@joao.silva'],
            'non-numeric dash text stays intact' => ['-cmd|calc', '-cmd|calc'],
            'leading tab stays intact' => ["\tSUM(A1:A2)", "\tSUM(A1:A2)"],
        ];
    }

    private function formatValue(mixed $value, string $type = ''): mixed
    {
        $export = (new ReflectionClass(CrudExport::class))->newInstanceWithoutConstructor();
        $method = (new ReflectionClass($export))->getMethod('formatValue');

        return $method->invoke($export, $value, $type);
    }
}
