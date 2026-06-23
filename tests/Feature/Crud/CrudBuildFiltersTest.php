<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use PHPUnit\Framework\Attributes\Test;
use Ptah\DTO\FilterDTO;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Tests\TestCase;

/**
 * Covers BaseCrud::buildActiveFilters() — the bridge from the filter-panel
 * state ($filters + $filterOperators + config) to FilterDTOs. Asserts the DTO
 * shape directly (no SQL / no Livewire render), proving:
 *   - relation columns: numeric id → FK filter; text → whereHas (item 4)
 *   - NULL operators keep a value-less filter (item 2)
 *   - empty operators fall back instead of becoming invalid (item 3)
 *
 * buildActiveFilters() only reads $filters / $filterOperators / $crudConfig and
 * builds DTOs — it never queries — so a bare component instance is enough.
 */
class CrudBuildFiltersTest extends TestCase
{
    private function config(): array
    {
        return [
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                // Relationship column: FK + relation name + related display column.
                [
                    'colsNomeFisico' => 'category_id',
                    'colsNomeLogico' => 'Category',
                    'colsTipo' => 'searchdropdown',
                    'colsGravar' => true,
                    'colsRelacao' => 'category',
                    'colsRelacaoExibe' => 'name',
                ],
            ],
            'customFilters' => [],
        ];
    }

    /**
     * Calls the protected buildActiveFilters() and returns the FilterDTO[].
     *
     * @return FilterDTO[]
     */
    private function build(array $filters, array $operators = []): array
    {
        $crud = new BaseCrud;
        $crud->crudConfig = $this->config();
        $crud->filters = $filters;
        $crud->filterOperators = $operators;

        $method = new \ReflectionMethod($crud, 'buildActiveFilters');
        $method->setAccessible(true);

        return $method->invoke($crud);
    }

    // ── Item 4: relationship column ──────────────────────────────────────────

    #[Test]
    public function numeric_value_on_a_relation_column_filters_the_fk_directly(): void
    {
        $dtos = $this->build(['category_id' => '5'], ['category_id' => '=']);

        $this->assertCount(1, $dtos);
        $this->assertSame('category_id', $dtos[0]->field);
        $this->assertSame('number', $dtos[0]->type);
        $this->assertSame('=', $dtos[0]->operator);
        $this->assertSame('5', $dtos[0]->value);
        $this->assertArrayNotHasKey('whereHas', $dtos[0]->options);
    }

    #[Test]
    public function not_equal_on_a_relation_column_keeps_the_fk_path(): void
    {
        $dtos = $this->build(['category_id' => '5'], ['category_id' => '!=']);

        $this->assertSame('category_id', $dtos[0]->field);
        $this->assertSame('number', $dtos[0]->type);
        $this->assertSame('!=', $dtos[0]->operator);
    }

    #[Test]
    public function text_value_on_a_relation_column_uses_where_has(): void
    {
        $dtos = $this->build(['category_id' => 'tools'], ['category_id' => 'LIKE']);

        $this->assertCount(1, $dtos);
        $this->assertSame('relation', $dtos[0]->type);
        $this->assertSame('category', $dtos[0]->options['whereHas']);
        $this->assertSame('name', $dtos[0]->options['column']);
        $this->assertSame('LIKE', $dtos[0]->operator);
        $this->assertSame('tools', $dtos[0]->value);
    }

    // ── Item 2: NULL operators ───────────────────────────────────────────────

    #[Test]
    public function null_operator_produces_a_value_less_filter(): void
    {
        // No entry in $filters — only the operator. Must still build a DTO.
        $dtos = $this->build([], ['name' => 'IS NULL']);

        $this->assertCount(1, $dtos);
        $this->assertSame('name', $dtos[0]->field);
        $this->assertSame('IS NULL', $dtos[0]->operator);
        $this->assertNull($dtos[0]->value);
    }

    #[Test]
    public function is_not_null_operator_is_preserved(): void
    {
        $dtos = $this->build([], ['status' => 'IS NOT NULL']);

        $this->assertCount(1, $dtos);
        $this->assertSame('IS NOT NULL', $dtos[0]->operator);
    }

    // ── Item 3: empty operator ───────────────────────────────────────────────

    #[Test]
    public function empty_operator_never_reaches_the_query_as_invalid(): void
    {
        $dtos = $this->build(['name' => 'abc'], ['name' => '']);

        $this->assertCount(1, $dtos);
        // Empty operator normalised → auto-detected (LIKE for multi-char text), never ''.
        $this->assertNotSame('', $dtos[0]->operator);
        $this->assertContains($dtos[0]->operator, ['LIKE', '=']);
    }

    #[Test]
    public function empty_value_without_a_null_operator_is_skipped(): void
    {
        $dtos = $this->build(['name' => ''], ['name' => '=']);

        $this->assertCount(0, $dtos);
    }
}
