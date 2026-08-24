<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * database/migrations/ (package root, NOT src/Migrations) holds the opt-in
 * "Camada 2" notifications schema. It exists precisely so it is NEVER
 * auto-discovered by PtahServiceProvider::loadMigrations() the way
 * src/Migrations is (see SchemaIsFrozenTest) — a consumer who enables an
 * unrelated module (company, permissions, …) must not have
 * `ptah_notifications` silently pushed onto their next `php artisan migrate`.
 *
 * This test pins two invariants:
 *  1. The manifest of database/migrations/ (same spirit as SchemaIsFrozenTest,
 *     but for a directory that is meant to grow only via a deliberate,
 *     reviewed addition — never silently).
 *  2. PtahServiceProvider has no loadMigrationsFrom() call pointing at
 *     database/migrations — a textual guard against the exact regression this
 *     directory's existence protects against (see the migration file's own
 *     docblock).
 */
class OptInSchemaIsFrozenTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private const SHIPPED = [
        '2026_08_23_000000_create_ptah_notifications_table.php',
    ];

    #[Test]
    public function the_package_ships_exactly_the_optin_migrations_it_shipped(): void
    {
        $found = self::discover();

        $added = array_values(array_diff($found, self::SHIPPED));
        $removed = array_values(array_diff(self::SHIPPED, $found));

        $this->assertSame(
            [],
            $added,
            "Migration NOVA em database/migrations/ sem atualizar este manifesto:\n  ".implode("\n  ", $added).
            "\n\nAtualize SHIPPED nesta mesma alteracao, com justificativa."
        );

        $this->assertSame(
            [],
            $removed,
            "Migration REMOVIDA de database/migrations/:\n  ".implode("\n  ", $removed).
            "\n\nUm consumidor que ja publicou essa migration depende dela."
        );
    }

    #[Test]
    public function the_service_provider_never_auto_loads_the_opt_in_migrations_directory(): void
    {
        $source = file_get_contents(self::providerPath());

        $this->assertIsString($source, 'OptInSchemaIsFrozenTest: falha ao ler PtahServiceProvider.php.');

        $this->assertStringNotContainsString(
            "loadMigrationsFrom(__DIR__.'/../database/migrations",
            $source,
            'PtahServiceProvider nao pode auto-carregar database/migrations/ — o schema de '.
            'notificacoes e opt-in via vendor:publish --tag=ptah-notifications, nunca auto-descoberto.'
        );

        $this->assertStringContainsString(
            "'ptah-notifications'",
            $source,
            'PtahServiceProvider deve expor a tag vendor:publish ptah-notifications.'
        );
    }

    /**
     * @return array<int, string>
     */
    private static function discover(): array
    {
        $root = self::migrationsDir();

        if (! is_dir($root)) {
            throw new RuntimeException('OptInSchemaIsFrozenTest: database/migrations nao encontrado.');
        }

        $found = [];

        foreach (glob($root.'/*.php') ?: [] as $path) {
            $found[] = basename($path);
        }

        sort($found);

        return $found;
    }

    private static function migrationsDir(): string
    {
        return dirname(__DIR__, 3).'/database/migrations';
    }

    private static function providerPath(): string
    {
        return dirname(__DIR__, 3).'/src/PtahServiceProvider.php';
    }
}
