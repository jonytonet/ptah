<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Services\Crud;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\DTO\FilterDTO;
use Ptah\Services\Crud\FilterService;
use Ptah\Tests\TestCase;

// ── Stub model on the `items` table ──────────────────────────────────────────

class FilterServiceItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Covers FilterService: AND/OR composition, date-range form parsing,
 * custom-filter config parsing and global search building. Assertions run
 * against real rows so the generated SQL is exercised, not just inspected.
 */
class FilterServiceTest extends TestCase
{
    private FilterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FilterService;

        FilterServiceItem::create(['name' => 'Alpha', 'status' => 'active', 'amount' => 10]);
        FilterServiceItem::create(['name' => 'Beta', 'status' => 'inactive', 'amount' => 20]);
        FilterServiceItem::create(['name' => 'Gamma', 'status' => 'active', 'amount' => 30]);
    }

    // ── NULL operators (item 2) ─────────────────────────────────────────────────

    #[Test]
    public function is_null_operator_helper_recognises_every_variant(): void
    {
        foreach (['IS NULL', 'is null', ' NOT NULL ', 'IS NOT NULL', 'null'] as $op) {
            $this->assertTrue(FilterService::isNullOperator($op), "[$op] should be a NULL operator");
        }

        foreach (['=', '!=', 'LIKE', '', null] as $op) {
            $this->assertFalse(FilterService::isNullOperator($op), var_export($op, true).' is not a NULL operator');
        }
    }

    #[Test]
    public function is_not_null_operator_filters_by_the_column_without_a_value(): void
    {
        // No value needed; every row has a non-null name.
        $rows = $this->service->applyFilters(FilterServiceItem::query(), [
            new FilterDTO(field: 'name', value: null, operator: 'IS NOT NULL', type: 'text'),
        ])->get();

        $this->assertCount(3, $rows);
    }

    #[Test]
    public function is_null_operator_filters_by_the_column_without_a_value(): void
    {
        // No row has a null name → empty result, proving whereNull was applied.
        $rows = $this->service->applyFilters(FilterServiceItem::query(), [
            new FilterDTO(field: 'name', value: null, operator: 'IS NULL', type: 'text'),
        ])->get();

        $this->assertCount(0, $rows);
    }

    #[Test]
    public function null_operator_works_inside_an_or_group(): void
    {
        // (name IS NULL) OR (name = Alpha) → just Alpha.
        $rows = $this->service->applyFilters(FilterServiceItem::query(), [
            new FilterDTO(field: 'name', value: null, operator: 'IS NULL', type: 'text', options: ['logic' => 'OR']),
            new FilterDTO(field: 'name', value: 'Alpha', operator: '=', type: 'text', options: ['logic' => 'OR']),
        ])->get();

        $this->assertSame(['Alpha'], $rows->pluck('name')->all());
    }

    #[Test]
    public function unsafe_field_on_a_null_operator_is_discarded(): void
    {
        $rows = $this->service->applyFilters(FilterServiceItem::query(), [
            new FilterDTO(field: 'name) OR 1=1 --', value: null, operator: 'IS NULL', type: 'text'),
        ])->get();

        // Guard rejects the field → no clause added → all rows returned.
        $this->assertCount(3, $rows);
    }

    // ── applyFilters ──────────────────────────────────────────────────────────

    #[Test]
    public function and_filters_are_combined_restrictively(): void
    {
        $rows = $this->service->applyFilters(FilterServiceItem::query(), [
            new FilterDTO(field: 'status', value: 'active', operator: '=', type: 'text'),
            new FilterDTO(field: 'amount', value: '15', operator: '>', type: 'number'),
        ])->get();

        // active AND amount > 15 → only Gamma
        $this->assertCount(1, $rows);
        $this->assertSame('Gamma', $rows->first()->name);
    }

    #[Test]
    public function or_filters_are_grouped_in_a_single_block(): void
    {
        $rows = $this->service->applyFilters(FilterServiceItem::query(), [
            new FilterDTO(field: 'name', value: 'Alpha', operator: '=', type: 'text', options: ['logic' => 'OR']),
            new FilterDTO(field: 'name', value: 'Beta', operator: '=', type: 'text', options: ['logic' => 'OR']),
        ])->get();

        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(['Alpha', 'Beta'], $rows->pluck('name')->all());
    }

    #[Test]
    public function and_and_or_filters_compose_correctly(): void
    {
        // status=active AND (name=Alpha OR name=Beta) → only Alpha
        $rows = $this->service->applyFilters(FilterServiceItem::query(), [
            new FilterDTO(field: 'status', value: 'active', operator: '=', type: 'text'),
            new FilterDTO(field: 'name', value: 'Alpha', operator: '=', type: 'text', options: ['logic' => 'OR']),
            new FilterDTO(field: 'name', value: 'Beta', operator: '=', type: 'text', options: ['logic' => 'OR']),
        ])->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Alpha', $rows->first()->name);
    }

    #[Test]
    public function invalid_filters_are_skipped_silently(): void
    {
        $rows = $this->service->applyFilters(FilterServiceItem::query(), [
            new FilterDTO(field: '', value: 'x', operator: '=', type: 'text'),       // no field
            new FilterDTO(field: 'name', value: null, operator: '=', type: 'text'),  // null value
            new FilterDTO(field: 'name', value: [], operator: 'IN', type: 'array'),  // empty array
        ])->get();

        $this->assertCount(3, $rows, 'Invalid filters must not restrict the query');
    }

    #[Test]
    public function plain_arrays_are_accepted_and_converted_to_dtos(): void
    {
        $rows = $this->service->applyFilters(FilterServiceItem::query(), [
            ['field' => 'status', 'value' => 'inactive', 'operator' => '=', 'type' => 'text'],
        ])->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Beta', $rows->first()->name);
    }

    // ── processDateRangeFilters ───────────────────────────────────────────────

    #[Test]
    public function start_and_end_pair_becomes_a_between_filter(): void
    {
        $filters = $this->service->processDateRangeFilters([
            'created_at_start' => '2026-01-01',
            'created_at_end' => '2026-01-31',
        ]);

        $this->assertCount(1, $filters);
        $this->assertSame('BETWEEN', $filters[0]->operator);
        $this->assertSame('created_at', $filters[0]->field);
        $this->assertSame(['2026-01-01', '2026-01-31'], $filters[0]->value);
    }

    #[Test]
    public function start_only_becomes_a_gte_filter(): void
    {
        $filters = $this->service->processDateRangeFilters(['created_at_start' => '2026-01-01']);

        $this->assertCount(1, $filters);
        $this->assertSame('>=', $filters[0]->operator);
        $this->assertSame('2026-01-01', $filters[0]->value);
    }

    #[Test]
    public function explicit_operators_produce_individual_filters(): void
    {
        $filters = $this->service->processDateRangeFilters(
            ['created_at_start' => '2026-01-01', 'created_at_end' => '2026-01-31'],
            ['created_at_start' => '>', 'created_at_end' => '<'],
        );

        $this->assertCount(2, $filters);
        $this->assertSame('>', $filters[0]->operator);
        $this->assertSame('<', $filters[1]->operator);
    }

    #[Test]
    public function legacy_from_to_pattern_is_still_supported(): void
    {
        $filters = $this->service->processDateRangeFilters([
            'created_at_from' => '2026-01-01',
            'created_at_to' => '2026-01-31',
        ]);

        $this->assertCount(1, $filters);
        $this->assertSame('BETWEEN', $filters[0]->operator);
    }

    // ── processCustomFilters ──────────────────────────────────────────────────

    #[Test]
    public function custom_filter_reads_value_from_form_data(): void
    {
        $filters = $this->service->processCustomFilters(
            [['field' => 'status', 'operator' => '=', 'type' => 'text']],
            ['status' => 'active'],
        );

        $this->assertCount(1, $filters);
        $this->assertSame('status', $filters[0]->field);
        $this->assertSame('active', $filters[0]->value);
    }

    #[Test]
    public function custom_filter_with_where_has_builds_relation_options(): void
    {
        $filters = $this->service->processCustomFilters(
            [['field' => 'supplier', 'whereHas' => 'supplier', 'colRelation' => 'name', 'operator' => 'LIKE']],
            ['supplier' => 'Acme'],
        );

        $this->assertCount(1, $filters);
        $this->assertSame('supplier', $filters[0]->options['whereHas']);
        $this->assertSame('name', $filters[0]->options['column']);
        $this->assertSame('name', $filters[0]->field, 'Field must be the relation column when whereHas is set');
    }

    #[Test]
    public function in_operator_with_csv_string_becomes_an_array_filter(): void
    {
        $filters = $this->service->processCustomFilters(
            [['field' => 'status', 'operator' => 'IN']],
            ['status' => 'active, inactive'],
        );

        $this->assertCount(1, $filters);
        $this->assertSame('array', $filters[0]->type);
        $this->assertSame(['active', 'inactive'], $filters[0]->value);
    }

    #[Test]
    public function empty_values_produce_no_custom_filters(): void
    {
        $filters = $this->service->processCustomFilters(
            [['field' => 'status', 'operator' => '=']],
            ['status' => ''],
        );

        $this->assertSame([], $filters);
    }

    // ── buildGlobalSearchFilters ──────────────────────────────────────────────

    #[Test]
    public function global_search_builds_or_like_filters_for_text_columns(): void
    {
        $cols = [
            ['colsNomeFisico' => 'name', 'colsTipo' => 'text'],
            ['colsNomeFisico' => 'status', 'colsTipo' => 'select'],
            ['colsNomeFisico' => 'amount', 'colsTipo' => 'number'],  // skipped
        ];

        $filters = $this->service->buildGlobalSearchFilters($cols, 'alp');

        $this->assertCount(2, $filters);
        foreach ($filters as $f) {
            $this->assertSame('OR', $f->options['logic']);
            $this->assertSame('LIKE', $f->operator);
        }

        // Applying them actually finds Alpha.
        $rows = $this->service->applyFilters(FilterServiceItem::query(), $filters)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Alpha', $rows->first()->name);
    }

    #[Test]
    public function global_search_uses_where_has_for_relation_columns(): void
    {
        $cols = [
            ['colsNomeFisico' => 'category_id', 'colsTipo' => 'text', 'colsRelacao' => 'category', 'colsRelacaoExibe' => 'name'],
        ];

        $filters = $this->service->buildGlobalSearchFilters($cols, 'tools');

        $this->assertCount(1, $filters);
        $this->assertSame('category', $filters[0]->options['whereHas']);
        $this->assertSame('name', $filters[0]->options['column']);
        $this->assertSame('relation', $filters[0]->type);
    }
}
