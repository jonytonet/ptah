<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Exceptions\ConfigValidationException;
use Ptah\Services\Validation\ConfigSchemaValidator;
use Ptah\Tests\TestCase;

/**
 * Covers ConfigSchemaValidator::validateStyles() — it must accept BOTH shapes
 * a `styles` rule can arrive in (canonical field/condition/value/style, and
 * the schema-legacy colsNomeFisico/colsOperator/colsValue/colsCss), via the
 * same StyleRule::normalize() the runtime and the CLI use, and reject
 * whichever normalises to null (e.g. an unrecognised condition).
 */
class ConfigSchemaValidatorStylesTest extends TestCase
{
    private function validator(): ConfigSchemaValidator
    {
        return new ConfigSchemaValidator;
    }

    #[Test]
    public function a_canonical_style_rule_passes(): void
    {
        $this->validator()->validate([
            'styles' => [
                ['field' => 'status', 'condition' => '==', 'value' => 'cancelled', 'style' => 'background:red;'],
            ],
        ], 'Widget');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function a_legacy_schema_style_rule_passes(): void
    {
        $this->validator()->validate([
            'styles' => [
                ['colsNomeFisico' => 'status', 'colsOperator' => 'eq', 'colsValue' => 'cancelled', 'colsCss' => 'background:red;'],
            ],
        ], 'Widget');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function an_unrecognised_condition_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'styles' => [
                ['field' => 'status', 'condition' => 'LIKE', 'value' => 'cancelled', 'style' => 'background:red;'],
            ],
        ], 'Widget');
    }

    #[Test]
    public function a_missing_field_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'styles' => [
                ['condition' => '==', 'value' => 'cancelled', 'style' => 'background:red;'],
            ],
        ], 'Widget');
    }

    #[Test]
    public function an_empty_style_throws(): void
    {
        $this->expectException(ConfigValidationException::class);

        $this->validator()->validate([
            'styles' => [
                ['field' => 'status', 'condition' => '==', 'value' => 'cancelled', 'style' => ''],
            ],
        ], 'Widget');
    }
}
