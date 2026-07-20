<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Exceptions\ConfigValidationException;
use Ptah\Services\Validation\ConfigSchemaValidator;
use Ptah\Tests\TestCase;

/**
 * Covers ConfigSchemaValidator::validateExportConfig() — additive, non-
 * breaking: an absent `exportConfig` section is valid (older configs never
 * had one); when present, only type/enum checks on the keys that ARE set.
 */
class ConfigSchemaValidatorExportTest extends TestCase
{
    private function validator(): ConfigSchemaValidator
    {
        return new ConfigSchemaValidator;
    }

    #[Test]
    public function an_absent_export_config_section_is_valid(): void
    {
        $this->validator()->validate(['cols' => []], 'Product');

        $this->addToAssertionCount(1); // no exception = pass
    }

    #[Test]
    public function a_fully_populated_export_config_is_valid(): void
    {
        $this->validator()->validate([
            'exportConfig' => [
                'enabled' => true,
                'maxRows' => 5000,
                'formats' => ['excel', 'pdf'],
                'asyncExport' => ['enabled' => true, 'excel' => true, 'pdf' => false],
            ],
        ], 'Product');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function unknown_keys_inside_export_config_are_ignored(): void
    {
        $this->validator()->validate([
            'exportConfig' => [
                'enabled' => true,
                'somethingFromTheFuture' => 'whatever',
                'asyncExport' => ['enabled' => false, 'somethingElse' => 123],
            ],
        ], 'Product');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function enabled_with_a_wrong_type_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'exportConfig' => ['enabled' => 'yes'],
        ], 'Product');
    }

    #[Test]
    public function max_rows_zero_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'exportConfig' => ['maxRows' => 0],
        ], 'Product');
    }

    #[Test]
    public function an_unknown_format_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'exportConfig' => ['formats' => ['excel', 'csv']],
        ], 'Product');
    }

    #[Test]
    public function async_export_enabled_with_a_wrong_type_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'exportConfig' => ['asyncExport' => ['enabled' => 'nope']],
        ], 'Product');
    }

    #[Test]
    public function async_export_excel_and_pdf_flags_are_validated_too(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'exportConfig' => ['asyncExport' => ['pdf' => 'nope']],
        ], 'Product');
    }
}
