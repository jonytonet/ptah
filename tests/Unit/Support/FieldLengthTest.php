<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\FieldDefinition;
use Ptah\Support\SchemaInspector;
use Ptah\Tests\TestCase;

/**
 * Regression for a bug found scaffolding 14 real entities: `string(60)` and
 * `char(2)` had their length parsed and then silently DROPPED, so every text
 * column became varchar(255) — while decimal(p,s) and enum(a|b) honoured their
 * params. Besides the wasted width, varchar(255) under InnoDB/utf8mb4 costs
 * 1020 bytes per indexed column and blows the 3072-byte limit on composite
 * indexes (exactly what the post-forge checklist asks the developer to add).
 */
class FieldLengthTest extends TestCase
{
    private function parse(string $definition): FieldDefinition
    {
        $fields = (new SchemaInspector)->fromString($definition);

        return $fields[0];
    }

    #[Test]
    public function string_keeps_its_declared_length(): void
    {
        $field = $this->parse('code:string(20)');

        $this->assertSame('string', $field->type);
        $this->assertSame(20, $field->length);
        $this->assertStringContainsString("\$table->string('code', 20)", $field->migrationLine());
    }

    #[Test]
    public function char_is_a_first_class_type_and_keeps_its_length(): void
    {
        $field = $this->parse('uf:char(2)');

        $this->assertSame('char', $field->type);
        $this->assertSame(2, $field->length);
        $this->assertStringContainsString("\$table->char('uf', 2)", $field->migrationLine());
    }

    #[Test]
    public function string_without_a_length_still_omits_it_and_lets_laravel_default(): void
    {
        $field = $this->parse('name:string');

        $this->assertNull($field->length);
        $this->assertStringContainsString("\$table->string('name')", $field->migrationLine());
    }

    #[Test]
    public function the_validation_rule_follows_the_declared_length(): void
    {
        $this->assertStringContainsString('max:20', $this->parse('code:string(20)')->validationRuleStore());
        $this->assertStringContainsString('max:2', $this->parse('uf:char(2)')->validationRuleStore());
        $this->assertStringContainsString('max:255', $this->parse('name:string')->validationRuleStore());
    }

    /**
     * The length must survive alongside the other modifiers — it is parsed from
     * the type's parentheses, not from the modifier segments.
     */
    #[Test]
    public function length_survives_other_modifiers(): void
    {
        $field = $this->parse('code:string(30):nullable:unique:surname=Code');

        $this->assertSame(30, $field->length);
        $this->assertTrue($field->nullable);
        $this->assertTrue($field->unique);
        $this->assertSame('Code', $field->label);
        $this->assertStringContainsString("\$table->string('code', 30)", $field->migrationLine());
    }

    /**
     * Guards the types that already honoured their parentheses — the fix must
     * not regress them.
     */
    #[Test]
    #[DataProvider('parameterisedTypesProvider')]
    public function parameterised_types_keep_working(string $definition, string $expected): void
    {
        $this->assertStringContainsString($expected, $this->parse($definition)->migrationLine());
    }

    public static function parameterisedTypesProvider(): array
    {
        return [
            'decimal keeps precision' => ['price:decimal(17,4)', "\$table->decimal('price', 17, 4)"],
            'enum keeps values' => ['status:enum(draft|synced)', "\$table->enum('status', ['draft', 'synced'])"],
            'text takes no length' => ['notes:text', "\$table->text('notes')"],
        ];
    }
}
