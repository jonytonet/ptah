<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config\Validators;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Validators\ConfigValidator;
use Ptah\Tests\TestCase;

/**
 * Covers ConfigValidator — the guardrails ptah:config runs over a parsed
 * column/join/action/filter/style/structure before it is persisted. Pure
 * validation (throws InvalidArgumentException), no DB.
 */
class ConfigValidatorTest extends TestCase
{
    private ConfigValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConfigValidator;
    }

    // ── validateColumn ───────────────────────────────────────────────────

    #[Test]
    public function accepts_a_minimal_valid_column(): void
    {
        $this->validator->validateColumn([
            'colsNomeFisico' => 'name',
            'colsTipo' => 'text',
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rejects_a_column_without_a_physical_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('colsNomeFisico is required');

        $this->validator->validateColumn(['colsNomeFisico' => '', 'colsTipo' => 'text']);
    }

    #[Test]
    public function rejects_an_unknown_column_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid colsTipo: bogus');

        $this->validator->validateColumn(['colsNomeFisico' => 'x', 'colsTipo' => 'bogus']);
    }

    #[Test]
    public function rejects_a_select_column_without_options(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Select type requires colsSelect');

        $this->validator->validateColumn(['colsNomeFisico' => 'status', 'colsTipo' => 'select']);
    }

    #[Test]
    public function accepts_a_select_column_with_options(): void
    {
        $this->validator->validateColumn([
            'colsNomeFisico' => 'status',
            'colsTipo' => 'select',
            'colsSelect' => 'active:Active',
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rejects_a_searchdropdown_column_without_model_or_service(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SearchDropdown requires colsSDModel or colsSDService');

        $this->validator->validateColumn(['colsNomeFisico' => 'supplier_id', 'colsTipo' => 'searchdropdown']);
    }

    #[Test]
    public function rejects_a_badge_renderer_without_badges_configured(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Badge/pill renderer requires colsRendererBadges');

        $this->validator->validateColumn([
            'colsNomeFisico' => 'status',
            'colsTipo' => 'text',
            'colsRenderer' => 'badge',
        ]);
    }

    #[Test]
    public function rejects_an_unknown_renderer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid renderer: bogus');

        $this->validator->validateColumn([
            'colsNomeFisico' => 'status',
            'colsTipo' => 'text',
            'colsRenderer' => 'bogus',
        ]);
    }

    #[Test]
    public function rejects_an_unknown_mask(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mask: bogus');

        $this->validator->validateColumn([
            'colsNomeFisico' => 'phone',
            'colsTipo' => 'text',
            'colsMask' => 'bogus',
        ]);
    }

    // ── validateJoin ─────────────────────────────────────────────────────

    #[Test]
    public function accepts_a_minimal_valid_join(): void
    {
        $this->validator->validateJoin([
            'type' => 'left',
            'table' => 'suppliers',
            'first' => 'products.supplier_id',
            'second' => 'suppliers.id',
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rejects_a_join_missing_a_required_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("JOIN: field 'table' is required");

        $this->validator->validateJoin([
            'type' => 'left',
            'table' => '',
            'first' => 'a',
            'second' => 'b',
        ]);
    }

    #[Test]
    public function rejects_an_unknown_join_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JOIN type: full');

        $this->validator->validateJoin([
            'type' => 'full',
            'table' => 'suppliers',
            'first' => 'a',
            'second' => 'b',
        ]);
    }

    #[Test]
    public function rejects_a_join_that_duplicates_an_already_used_table(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Table 'suppliers' is already used in another JOIN");

        $this->validator->validateJoin(
            ['type' => 'left', 'table' => 'suppliers', 'first' => 'a', 'second' => 'b'],
            [['table' => 'suppliers']]
        );
    }

    // ── validateAction ───────────────────────────────────────────────────

    #[Test]
    public function accepts_a_minimal_valid_action(): void
    {
        $this->validator->validateAction([
            'colsNomeLogico' => 'Approve',
            'actionType' => 'livewire',
            'actionValue' => 'approve(%id%)',
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rejects_an_action_without_a_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Action name (colsNomeLogico) is required');

        $this->validator->validateAction(['actionType' => 'livewire', 'actionValue' => 'x']);
    }

    #[Test]
    public function rejects_an_action_with_an_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid action type: bogus');

        $this->validator->validateAction([
            'colsNomeLogico' => 'Approve',
            'actionType' => 'bogus',
            'actionValue' => 'x',
        ]);
    }

    #[Test]
    public function rejects_an_action_with_an_unknown_color(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid action color: bogus');

        $this->validator->validateAction([
            'colsNomeLogico' => 'Approve',
            'actionType' => 'livewire',
            'actionValue' => 'x',
            'actionColor' => 'bogus',
        ]);
    }

    // ── validateFilter ───────────────────────────────────────────────────

    #[Test]
    public function accepts_a_minimal_valid_filter(): void
    {
        $this->validator->validateFilter(['field' => 'status']);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rejects_a_filter_without_a_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Filter field is required');

        $this->validator->validateFilter(['field' => '']);
    }

    #[Test]
    public function rejects_a_filter_with_an_unknown_type(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid filter type: bogus');

        $this->validator->validateFilter(['field' => 'status', 'colsFilterType' => 'bogus']);
    }

    #[Test]
    public function rejects_a_filter_with_an_unknown_operator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid operator: bogus');

        $this->validator->validateFilter(['field' => 'status', 'defaultOperator' => 'bogus']);
    }

    #[Test]
    public function rejects_a_filter_with_an_unknown_aggregate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid aggregate: bogus');

        $this->validator->validateFilter(['field' => 'amount', 'aggregate' => 'bogus']);
    }

    // ── validateStyle ────────────────────────────────────────────────────

    #[Test]
    public function accepts_a_minimal_valid_style(): void
    {
        $this->validator->validateStyle([
            'field' => 'status',
            'condition' => '==',
            'value' => 'cancelled',
            'style' => 'color:red;',
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rejects_a_style_missing_a_required_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Style: field 'value' is required");

        $this->validator->validateStyle([
            'field' => 'status',
            'condition' => '==',
            'style' => 'color:red;',
        ]);
    }

    #[Test]
    public function accepts_falsy_but_present_style_values(): void
    {
        // isset() (not empty()) guards the required fields, so '' / 0 must pass.
        $this->validator->validateStyle([
            'field' => 'qty',
            'condition' => '==',
            'value' => '0',
            'style' => '',
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rejects_a_style_with_an_unknown_condition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid style condition: bogus');

        $this->validator->validateStyle([
            'field' => 'status',
            'condition' => 'bogus',
            'value' => 'cancelled',
            'style' => 'color:red;',
        ]);
    }

    /**
     * BUG FIX (Onda 4 Parte B): validateStyle used to check the condition
     * against CrudConfigEnums::OPERATORS (the SQL-filter list: '=', 'LIKE',
     * ...) instead of the runtime's own set. HasCrudRenderers::getRowStyle()
     * only recognises '==', '!=', '>', '<', '>=', '<=' — a style saved with
     * '=' or 'LIKE' used to pass this validator and then silently never
     * apply at render time (the match() default arm returns false, with no
     * error anywhere). These two cases pin the fix: '=' and 'LIKE' are now
     * REJECTED at validation time instead of failing silently later, and the
     * StyleParser's own docblock example condition ('==') now validates.
     */
    #[Test]
    public function rejects_the_sql_style_equals_operator_the_style_runtime_never_matches(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid style condition: =');

        $this->validator->validateStyle([
            'field' => 'status',
            'condition' => '=',
            'value' => 'cancelled',
            'style' => 'color:red;',
        ]);
    }

    #[Test]
    public function rejects_the_like_operator_the_style_runtime_never_matches(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid style condition: LIKE');

        $this->validator->validateStyle([
            'field' => 'status',
            'condition' => 'LIKE',
            'value' => 'cancel%',
            'style' => 'color:red;',
        ]);
    }

    // ── validateStructure ────────────────────────────────────────────────

    #[Test]
    public function accepts_a_structure_with_an_empty_form_edit_fields_array(): void
    {
        $this->validator->validateStructure(['formEditFields' => []]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function rejects_a_structure_missing_form_edit_fields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Configuration must have 'formEditFields' key");

        $this->validator->validateStructure([]);
    }

    #[Test]
    public function rejects_a_structure_where_form_edit_fields_is_not_an_array(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("'formEditFields' must be an array");

        $this->validator->validateStructure(['formEditFields' => 'not-an-array']);
    }
}
