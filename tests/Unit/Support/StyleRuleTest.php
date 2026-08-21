<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\StyleRule;
use Ptah\Tests\TestCase;

/**
 * StyleRule::normalize() is the single place that decides whether a
 * conditional row-style rule is usable by HasCrudRenderers::getRowStyle() —
 * collapsing the canonical shape and every legacy alias into one, and
 * rejecting (null) anything the runtime's match() expression could never
 * actually match (e.g. 'LIKE', which has no arm there).
 */
class StyleRuleTest extends TestCase
{
    #[Test]
    public function canonical_shape_passes_through_unchanged(): void
    {
        $rule = ['field' => 'status', 'condition' => '==', 'value' => 'cancelled', 'style' => 'background:#FEE2E2;'];

        $this->assertSame($rule, StyleRule::normalize($rule));
    }

    #[Test]
    public function schema_legacy_keys_are_mapped_to_the_canonical_shape(): void
    {
        $rule = [
            'colsNomeFisico' => 'status',
            'colsOperator' => 'eq',
            'colsValue' => 'cancelled',
            'colsCss' => 'background:#FEE2E2;',
        ];

        $this->assertSame(
            ['field' => 'status', 'condition' => '==', 'value' => 'cancelled', 'style' => 'background:#FEE2E2;'],
            StyleRule::normalize($rule)
        );
    }

    #[Test]
    public function style_field_alias_is_mapped_to_field(): void
    {
        $rule = ['styleField' => 'status', 'condition' => '==', 'value' => 'x', 'style' => 'color:red;'];

        $this->assertSame('status', StyleRule::normalize($rule)['field']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function conditionAliases(): array
    {
        return [
            'eq' => ['eq', '=='],
            'ne' => ['ne', '!='],
            'lt' => ['lt', '<'],
            'gt' => ['gt', '>'],
            'lte' => ['lte', '<='],
            'gte' => ['gte', '>='],
            '=' => ['=', '=='],
            '==' => ['==', '=='],
            '!=' => ['!=', '!='],
            '>' => ['>', '>'],
            '<' => ['<', '<'],
            '>=' => ['>=', '>='],
            '<=' => ['<=', '<='],
        ];
    }

    #[Test]
    #[DataProvider('conditionAliases')]
    public function condition_aliases_and_symbols_normalise_to_the_php_style_operator(string $input, string $expected): void
    {
        $rule = ['field' => 'status', 'condition' => $input, 'value' => 'x', 'style' => 'color:red;'];

        $this->assertSame($expected, StyleRule::normalize($rule)['condition']);
    }

    #[Test]
    public function like_operator_is_rejected(): void
    {
        $rule = ['field' => 'status', 'condition' => 'LIKE', 'value' => 'x', 'style' => 'color:red;'];

        $this->assertNull(StyleRule::normalize($rule));
    }

    #[Test]
    public function unknown_condition_is_rejected(): void
    {
        $rule = ['field' => 'status', 'condition' => 'contains', 'value' => 'x', 'style' => 'color:red;'];

        $this->assertNull(StyleRule::normalize($rule));
    }

    #[Test]
    public function value_zero_is_preserved(): void
    {
        $rule = ['field' => 'amount', 'condition' => '==', 'value' => 0, 'style' => 'color:red;'];

        $this->assertSame(0, StyleRule::normalize($rule)['value']);
    }

    #[Test]
    public function value_empty_string_is_preserved(): void
    {
        $rule = ['field' => 'status', 'condition' => '==', 'value' => '', 'style' => 'color:red;'];

        $this->assertSame('', StyleRule::normalize($rule)['value']);
    }

    #[Test]
    public function missing_value_key_normalises_to_null(): void
    {
        $rule = ['field' => 'status', 'condition' => '==', 'style' => 'color:red;'];

        $this->assertNull(StyleRule::normalize($rule)['value']);
    }

    #[Test]
    public function empty_field_is_rejected(): void
    {
        $rule = ['field' => '', 'condition' => '==', 'value' => 'x', 'style' => 'color:red;'];

        $this->assertNull(StyleRule::normalize($rule));
    }

    #[Test]
    public function missing_field_is_rejected(): void
    {
        $rule = ['condition' => '==', 'value' => 'x', 'style' => 'color:red;'];

        $this->assertNull(StyleRule::normalize($rule));
    }

    #[Test]
    public function empty_style_is_rejected(): void
    {
        $rule = ['field' => 'status', 'condition' => '==', 'value' => 'x', 'style' => ''];

        $this->assertNull(StyleRule::normalize($rule));
    }

    #[Test]
    public function missing_condition_defaults_to_equals(): void
    {
        $rule = ['field' => 'status', 'value' => 'x', 'style' => 'color:red;'];

        $this->assertSame('==', StyleRule::normalize($rule)['condition']);
    }
}
