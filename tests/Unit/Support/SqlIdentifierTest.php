<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Support\SqlIdentifier;

/**
 * Guards the SQL-identifier validation used to block injection through
 * dynamically-built raw SQL (filter column names).
 */
class SqlIdentifierTest extends TestCase
{
    #[Test]
    #[DataProvider('safeIdentifiers')]
    public function it_accepts_plain_and_table_qualified_identifiers(string $identifier): void
    {
        $this->assertTrue(SqlIdentifier::isSafe($identifier));
    }

    #[Test]
    #[DataProvider('unsafeIdentifiers')]
    public function it_rejects_anything_that_could_break_out_of_a_raw_clause(?string $identifier): void
    {
        $this->assertFalse(SqlIdentifier::isSafe($identifier));
    }

    public static function safeIdentifiers(): array
    {
        return [
            ['name'],
            ['created_at'],
            ['users.name'],
            ['_private'],
            ['Table1.Column2'],
        ];
    }

    public static function unsafeIdentifiers(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'injection' => ['name) OR 1=1 -- '],
            'space' => ['user name'],
            'quote' => ["name'"],
            'paren' => ['count(*)'],
            'two dots' => ['a.b.c'],
            'leading digit' => ['1name'],
            'semicolon' => ['name; DROP TABLE users'],
        ];
    }
}
