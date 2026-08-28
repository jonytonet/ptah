<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Base;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Schema introspection must go through the per-table cache, never straight to
 * the facade.
 *
 * `advancedSearch()` called `Schema::getColumnListing()` directly while a
 * cached `getTableColumns()` sat in the same class, so `getData(search)` paid
 * two schema round-trips on EVERY call — measured as 4 queries where plain
 * Eloquent emits 2 (1.27.0 benchmark). The relation-search path did the same,
 * under a comment that claimed a cache it did not implement.
 *
 * A source guard rather than a runtime one: the cost is invisible in a
 * green suite (the queries succeed, they are just wasted), and on MySQL the
 * facade hits information_schema, which is slow on a database with many
 * tables.
 */
class BaseRepositorySchemaCacheTest extends TestCase
{
    private const SOURCE = 'src/Base/BaseRepository.php';

    /**
     * Comments are stripped before scanning: this file's own docblocks name
     * `Schema::getColumnListing` while explaining the rule, and a guard that
     * trips on its own prose is this repository's most recurrent false failure.
     */
    private static function codeWithoutComments(): string
    {
        $path = dirname(__DIR__, 3).'/'.self::SOURCE;
        $code = file_get_contents($path);

        if ($code === false) {
            throw new RuntimeException('BaseRepositorySchemaCacheTest: falha ao ler '.self::SOURCE);
        }

        $out = '';

        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $out .= is_array($token) ? $token[1] : $token;
        }

        return $out;
    }

    #[Test]
    public function schema_introspection_happens_in_exactly_one_place(): void
    {
        $calls = preg_match_all('/Schema::getColumnListing\s*\(/', self::codeWithoutComments());

        $this->assertSame(
            1,
            $calls,
            'Schema::getColumnListing deve ser chamado SO dentro de columnsForTable() (o cache por tabela). '.
            "Cada chamada direta custa uma ida ao banco por request — no MySQL, information_schema.\n".
            "Encontradas: {$calls}."
        );
    }

    #[Test]
    public function the_cache_helper_exists_and_is_shared(): void
    {
        $code = self::codeWithoutComments();

        // static (não por instância): uma busca em relação precisa das colunas
        // da tabela RELACIONADA, não das deste repositório.
        $this->assertMatchesRegularExpression(
            '/protected\s+static\s+function\s+columnsForTable\s*\(\s*string\s+\$table\s*\)/',
            $code,
            'columnsForTable(string $table) precisa existir e ser static — o cache e compartilhado entre tabelas.'
        );

        $this->assertStringContainsString(
            'static $cache = []',
            $code,
            'O helper precisa manter o cache por tabela.'
        );
    }
}
