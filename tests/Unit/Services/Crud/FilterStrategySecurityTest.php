<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Services\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\DTO\FilterDTO;
use Ptah\Services\Crud\Filters\NumericFilterStrategy;
use Ptah\Services\Crud\Filters\RelationFilterStrategy;
use Ptah\Services\Crud\Filters\TextFilterStrategy;
use Ptah\Tests\TestCase;

// ── Stub model targeting the `items` table created by the test migration ─────

class FilterSecurityStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];

    /** Self-referencing relation used by the whereHas tests (query built, not executed). */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Regression tests for the SQL-injection guards added to the filter strategies.
 *
 * Each strategy uses SqlIdentifier::isSafe() before interpolating a column name
 * into raw SQL. These tests verify that malicious column names are silently
 * discarded while valid ones are applied.
 */
class FilterStrategySecurityTest extends TestCase
{
    // ── TextFilterStrategy ────────────────────────────────────────────────────

    #[Test]
    #[DataProvider('maliciousFieldNames')]
    public function text_strategy_discards_unsafe_field(string $field): void
    {
        $strategy = new TextFilterStrategy;
        $filter = new FilterDTO(field: $field, value: 'test', operator: 'LIKE');

        $query = FilterSecurityStub::query();
        $result = $strategy->apply($query, $filter);

        $this->assertEmpty(
            $result->getQuery()->wheres,
            "Unsafe field [{$field}] should be discarded — no WHERE clause must be added",
        );
    }

    #[Test]
    public function text_strategy_applies_like_with_safe_field(): void
    {
        $strategy = new TextFilterStrategy;
        $filter = new FilterDTO(field: 'name', value: 'foo', operator: 'LIKE');

        $result = $strategy->apply(FilterSecurityStub::query(), $filter);

        $this->assertNotEmpty($result->getQuery()->wheres);
    }

    #[Test]
    public function text_strategy_accepts_table_qualified_field(): void
    {
        $strategy = new TextFilterStrategy;
        $filter = new FilterDTO(field: 'items.name', value: 'bar', operator: '=');

        $result = $strategy->apply(FilterSecurityStub::query(), $filter);

        $this->assertNotEmpty($result->getQuery()->wheres);
    }

    #[Test]
    public function text_strategy_discards_null_value(): void
    {
        $strategy = new TextFilterStrategy;
        $filter = new FilterDTO(field: 'name', value: null, operator: 'LIKE');

        $result = $strategy->apply(FilterSecurityStub::query(), $filter);

        $this->assertEmpty($result->getQuery()->wheres);
    }

    // ── NumericFilterStrategy ─────────────────────────────────────────────────

    #[Test]
    #[DataProvider('maliciousFieldNames')]
    public function numeric_strategy_discards_unsafe_field(string $field): void
    {
        $strategy = new NumericFilterStrategy;
        $filter = new FilterDTO(field: $field, value: '5', operator: '>');

        $result = $strategy->apply(FilterSecurityStub::query(), $filter);

        $this->assertEmpty(
            $result->getQuery()->wheres,
            "Unsafe field [{$field}] should be discarded — no WHERE clause must be added",
        );
    }

    #[Test]
    public function numeric_strategy_applies_with_safe_field(): void
    {
        $strategy = new NumericFilterStrategy;
        $filter = new FilterDTO(field: 'amount', value: '10', operator: '>=');

        $result = $strategy->apply(FilterSecurityStub::query(), $filter);

        $this->assertNotEmpty($result->getQuery()->wheres);
    }

    // ── RelationFilterStrategy ────────────────────────────────────────────────

    #[Test]
    #[DataProvider('maliciousFieldNames')]
    public function relation_aggregate_strategy_ignores_unsafe_column(string $col): void
    {
        $strategy = new RelationFilterStrategy;
        $filter = new FilterDTO(
            field: 'id',
            value: '5',
            operator: '>',
            options: [
                'whereHas' => 'children',
                'column' => $col,
                'aggregate' => 'count',
                'aggregateColumn' => $col,
            ],
        );

        // Guard must not throw — it just skips the unsafe column inside the closure.
        $result = $strategy->apply(FilterSecurityStub::query(), $filter);

        $this->assertNotNull($result);
    }

    // ── Shared data ───────────────────────────────────────────────────────────

    public static function maliciousFieldNames(): array
    {
        return [
            'injection' => ['name) OR 1=1 --'],
            'semicolon' => ['name; DROP TABLE items'],
            'leading-digit' => ['1name'],
            'space' => ['col alias'],
            'single-quote' => ["col'"],
            'empty' => [''],
        ];
    }
}
