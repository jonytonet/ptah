<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\FieldDefinition;
use Ptah\Support\SchemaInspector;
use Ptah\Tests\TestCase;

/**
 * 1.47.0 (Engepecas): `timeout_seconds:unsignedSmallInteger` became a string
 * column, a string cast and a string DTO property, with no warning.
 */
class FieldTypeTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function integers(): array
    {
        return [
            'unsignedSmallInteger' => ['unsignedSmallInteger', 'unsignedSmallInteger'],
            'unsignedTinyInteger' => ['unsignedTinyInteger', 'unsignedTinyInteger'],
            'unsignedMediumInteger' => ['unsignedMediumInteger', 'unsignedMediumInteger'],
            'mediumInteger' => ['mediumInteger', 'mediumInteger'],
            'case-insensitive' => ['UNSIGNEDSMALLINTEGER', 'unsignedSmallInteger'],
        ];
    }

    #[Test]
    #[DataProvider('integers')]
    public function integer_types_stay_integers(string $given, string $canonical): void
    {
        $field = (new SchemaInspector)->fromString("timeout_seconds:{$given}")[0];

        $this->assertSame($canonical, $field->type);
        $this->assertStringContainsString("\$table->{$canonical}('timeout_seconds')", $field->migrationLine());
        $this->assertSame('integer', $field->castType());
        $this->assertSame('int', $field->phpType());
        $this->assertStringContainsString('integer', $field->validationRuleStore());
    }

    #[Test]
    public function an_unknown_type_is_refused_not_turned_into_a_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown field type "unsignedSmalInteger"');

        (new SchemaInspector)->fromString('timeout_seconds:unsignedSmalInteger');
    }

    #[Test]
    public function forge_fails_cleanly_on_an_unknown_type(): void
    {
        $this->artisan('ptah:forge', ['entity' => 'Widget', '--fields' => 'n:strng'])
            ->expectsOutputToContain('Unknown field type "strng"')
            ->assertFailed();
    }

    #[Test]
    public function every_declared_type_has_a_migration_line(): void
    {
        foreach (array_diff(FieldDefinition::TYPES, ['string']) as $type) {
            $field = new FieldDefinition(name: 'x', type: $type, nullable: false, unique: false, precision: 10, scale: 2, enumValues: ['a']);
            $this->assertStringNotContainsString("string('x')", $field->migrationLine(), "{$type} caiu no default string.");
        }
    }

    /** 1.50.1: "(kg)" in the label was read as the type's params. */
    #[Test]
    public function a_label_with_parentheses_does_not_eat_the_type_params(): void
    {
        $field = (new SchemaInspector)->fromString('weight:decimal(5,2):nullable:surname=Peso (kg)')[0];

        $this->assertSame(5, $field->precision);
        $this->assertSame(2, $field->scale);
        $this->assertSame('Peso (kg)', $field->label);
        $this->assertTrue($field->nullable);
    }

    #[Test]
    public function double_stays_double(): void
    {
        $this->assertSame('double', (new SchemaInspector)->fromString('ratio:double')[0]->type);
    }
}
