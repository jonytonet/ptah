<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\CrudConfigGenerator;
use Ptah\Models\CrudConfig;

/**
 * Covers the database-backed CRUD config: column mapping per field type,
 * the web-only shouldRun gate and idempotency without --force.
 */
class CrudConfigGeneratorTest extends GeneratorTestCase
{
    #[Test]
    public function it_persists_a_config_row_with_mapped_columns(): void
    {
        $result = (new CrudConfigGenerator($this->files))->generate($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');

        $row = CrudConfig::where('model', 'Widget')->first();
        $this->assertNotNull($row, 'crud_configs row must exist for Widget');

        $cols = collect($row->config['cols']);

        // id column is always first and read-only
        $this->assertSame('id', $cols->first()['colsNomeFisico']);
        $this->assertFalse($cols->first()['colsGravar']);

        // string → text, decimal → number with currency helper, FK → relation keys
        $name = $cols->firstWhere('colsNomeFisico', 'name');
        $this->assertSame('text', $name['colsTipo']);

        $price = $cols->firstWhere('colsNomeFisico', 'price');
        $this->assertSame('number', $price['colsTipo']);
        $this->assertSame('currencyFormat', $price['colsHelper'] ?? null);

        $fk = $cols->firstWhere('colsNomeFisico', 'category_id');
        $this->assertArrayHasKey('colsRelacao', $fk);

        // RBAC gate key: must be written under the canonical 'permissionIdentifier'
        // (the runtime key), never the legacy 'identifier'.
        $this->assertSame('pageWidget', $row->config['permissions']['permissionIdentifier']);
        $this->assertArrayNotHasKey('identifier', $row->config['permissions']);
    }

    #[Test]
    public function it_skips_when_a_config_already_exists_without_force(): void
    {
        $generator = new CrudConfigGenerator($this->files);

        $first = $generator->generate($this->context());
        $second = $generator->generate($this->context());

        $this->assertTrue($first->isDone());
        $this->assertTrue($second->isSkipped());
        $this->assertSame(1, CrudConfig::where('model', 'Widget')->count());
    }

    #[Test]
    public function it_does_not_run_in_api_only_mode(): void
    {
        $generator = new CrudConfigGenerator($this->files);

        $this->assertFalse($generator->shouldRun($this->context(withApi: true, withViews: false)));
        $this->assertTrue($generator->shouldRun($this->context()));
    }
}
