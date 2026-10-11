<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\SchemaInspector;
use Ptah\Tests\TestCase;

/**
 * 1.43.3 (projeto Cher): `status:string:default(x):index` gerou a coluna sem o
 * indice — o modificador nao existia e era ignorado sem aviso. E o
 * `decimal(5,2)` no meio de uma lista: o parser ja o separava certo (o
 * `decimal(..., 0, 2)` relatado vem do PowerShell sem aspas).
 */
class FieldIndexModifierTest extends TestCase
{
    #[Test]
    public function the_index_modifier_reaches_the_migration_with_the_default(): void
    {
        $fields = (new SchemaInspector)->fromString('name:string,fee_percent_override:decimal(5,2):nullable,status:string:default(open):index');

        $this->assertSame(['name', 'fee_percent_override', 'status'], array_map(fn ($f) => $f->name, $fields));
        $this->assertStringContainsString("decimal('fee_percent_override', 5, 2)->nullable()", $fields[1]->migrationLine());
        $this->assertStringContainsString("string('status')->index()->default('open')", $fields[2]->migrationLine());
        $this->assertStringNotContainsString('->index()', $fields[0]->migrationLine());
    }

    #[Test]
    public function unique_already_indexes_so_index_is_not_doubled(): void
    {
        $field = (new SchemaInspector)->fromString('email:string:unique:index')[0];

        $this->assertSame(1, substr_count($field->migrationLine(), 'unique()') + substr_count($field->migrationLine(), 'index()'));
    }
}
