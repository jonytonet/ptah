<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Services\Crud;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\DTO\FilterDTO;
use Ptah\Services\Crud\Filters\ArrayFilterStrategy;
use Ptah\Services\Crud\Filters\DateFilterStrategy;
use Ptah\Services\Crud\Filters\NumericFilterStrategy;
use Ptah\Tests\TestCase;

// ── Stub model on the `items` table ──────────────────────────────────────────

class StrategyItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Covers the remaining filter strategies (Numeric, Date, Array) against real
 * rows. Text and Relation are covered by FilterStrategySecurityTest.
 */
class FilterStrategiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        StrategyItem::create(['name' => 'Low', 'status' => 'active', 'amount' => 5]);
        StrategyItem::create(['name' => 'Mid', 'status' => 'inactive', 'amount' => 50]);
        StrategyItem::create(['name' => 'High', 'status' => 'active', 'amount' => 500]);
    }

    // ── NumericFilterStrategy ─────────────────────────────────────────────────

    #[Test]
    public function numeric_array_value_becomes_between(): void
    {
        $rows = (new NumericFilterStrategy)->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'amount', value: ['10', '100'], operator: '=', type: 'number'),
        )->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Mid', $rows->first()->name);
    }

    #[Test]
    public function numeric_partial_range_applies_only_the_filled_bound(): void
    {
        // Only "from" → >=
        $rows = (new NumericFilterStrategy)->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'amount', value: ['40', ''], operator: '=', type: 'number'),
        )->get();

        $this->assertEqualsCanonicalizing(['Mid', 'High'], $rows->pluck('name')->all());

        // Only "to" → <=
        $rows = (new NumericFilterStrategy)->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'amount', value: ['', '40'], operator: '=', type: 'number'),
        )->get();

        $this->assertSame(['Low'], $rows->pluck('name')->all());
    }

    #[Test]
    public function numeric_between_accepts_a_csv_string(): void
    {
        $rows = (new NumericFilterStrategy)->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'amount', value: '10, 100', operator: 'BETWEEN', type: 'number'),
        )->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Mid', $rows->first()->name);
    }

    #[Test]
    public function numeric_comparison_operators_work(): void
    {
        $rows = (new NumericFilterStrategy)->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'amount', value: '50', operator: '>', type: 'number'),
        )->get();

        $this->assertSame(['High'], $rows->pluck('name')->all());
    }

    // ── DateFilterStrategy ────────────────────────────────────────────────────

    #[Test]
    public function date_range_expands_to_start_and_end_of_day(): void
    {
        // Row created today — a range covering today must include all 3 rows.
        $today = now()->toDateString();

        $rows = (new DateFilterStrategy('datetime'))->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'created_at', value: [$today, $today], operator: 'BETWEEN', type: 'date'),
        )->get();

        $this->assertCount(3, $rows, 'A same-day range must cover the entire day (startOfDay..endOfDay)');
    }

    #[Test]
    public function date_range_excludes_rows_outside_the_window(): void
    {
        $rows = (new DateFilterStrategy('datetime'))->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'created_at', value: ['2000-01-01', '2000-12-31'], operator: 'BETWEEN', type: 'date'),
        )->get();

        $this->assertCount(0, $rows);
    }

    #[Test]
    public function date_equality_uses_where_date(): void
    {
        $rows = (new DateFilterStrategy('date'))->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'created_at', value: now()->toDateString(), operator: '=', type: 'date'),
        )->get();

        $this->assertCount(3, $rows);
    }

    #[Test]
    public function date_null_value_is_a_no_op(): void
    {
        $rows = (new DateFilterStrategy('date'))->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'created_at', value: null, operator: '=', type: 'date'),
        )->get();

        $this->assertCount(3, $rows);
    }

    // ── ArrayFilterStrategy ───────────────────────────────────────────────────

    #[Test]
    public function array_strategy_applies_where_in(): void
    {
        $rows = (new ArrayFilterStrategy)->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'name', value: ['Low', 'High'], operator: 'IN', type: 'array'),
        )->get();

        $this->assertEqualsCanonicalizing(['Low', 'High'], $rows->pluck('name')->all());
    }

    #[Test]
    public function array_strategy_normalises_csv_strings(): void
    {
        $rows = (new ArrayFilterStrategy)->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'name', value: 'Low, High, ', operator: 'IN', type: 'array'),
        )->get();

        $this->assertEqualsCanonicalizing(['Low', 'High'], $rows->pluck('name')->all());
    }

    #[Test]
    public function array_strategy_supports_not_in(): void
    {
        $rows = (new ArrayFilterStrategy)->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'name', value: ['Low'], operator: 'NOT IN', type: 'array'),
        )->get();

        $this->assertEqualsCanonicalizing(['Mid', 'High'], $rows->pluck('name')->all());
    }

    #[Test]
    public function array_strategy_ignores_empty_values(): void
    {
        $rows = (new ArrayFilterStrategy)->apply(
            StrategyItem::query(),
            new FilterDTO(field: 'name', value: ' , ', operator: 'IN', type: 'array'),
        )->get();

        $this->assertCount(3, $rows, 'A CSV of blanks must be a no-op, not an empty IN ()');
    }
}
