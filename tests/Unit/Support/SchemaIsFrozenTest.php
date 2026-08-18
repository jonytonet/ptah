<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The package's schema is frozen: an UPDATE must never require a migration.
 *
 * Installing ptah runs migrations, and that is fine — it is part of setting the
 * package up. Shipping a NEW migration in an update is not, and the reason is
 * sharper than "the consumer has to remember": PtahServiceProvider calls
 * loadMigrationsFrom(), so package migrations are AUTO-DISCOVERED. A new file does
 * not wait to be asked for — it executes on the consumer's next `php artisan
 * migrate`, run for their own unrelated reasons, in whatever environment that is.
 * Schema shipped that way is a side effect, not a decision.
 *
 * Existing installations also simply will not re-run migrate. A capability that
 * needs a new column is therefore a capability those installations never get.
 *
 * WHEN YOU NEED SOMETHING THE SCHEMA DOES NOT HAVE, in order of preference:
 *
 *  1. Express the capability as a page_object and grant `read` on it. This is how
 *     `crud.config` and `ai.config` work — the object IS the permission, so no verb
 *     and no column are needed. See the comments on ptah_can_manage_config() in
 *     src/helpers.php, which exist because an earlier attempt added a can_manage
 *     column and had to be reverted.
 *  2. Use a column that already exists. ptah_role_permissions.extra is a nullable
 *     JSON bag, declared and currently unread. Weigh it carefully: splitting one
 *     flag into JSON while its siblings live in columns creates an asymmetry between
 *     two code paths, and every authorisation defect found in the audit came from
 *     exactly that kind of split.
 *  3. Only if neither works, add the migration AND update the manifest below in the
 *     same commit. That makes it a reviewed decision with a release note attached,
 *     instead of something a consumer discovers after the fact.
 *
 * Deletions fail too: removing a migration breaks fresh installs.
 */
class SchemaIsFrozenTest extends TestCase
{
    /**
     * Every migration the package ships. All 17 already existed in v1.13.2, the last
     * published tag — so nothing since then has changed a consumer's schema.
     *
     * @var array<int, string>
     */
    private const SHIPPED = [
        '2024_01_01_000000_create_user_preferences_table.php',
        '2024_01_01_000001_create_crud_configs_table.php',
        '2024_01_03_000000_create_menus_table.php',
        '2024_01_03_000001_add_two_factor_columns_to_users_table.php',
        '2024_01_04_000000_create_ptah_companies_table.php',
        '2024_01_04_000001_create_ptah_departments_table.php',
        '2024_01_04_000002_create_ptah_roles_table.php',
        '2024_01_04_000003_create_ptah_pages_table.php',
        '2024_01_04_000004_create_ptah_page_objects_table.php',
        '2024_01_04_000005_create_ptah_role_permissions_table.php',
        '2024_01_04_000006_create_ptah_user_roles_table.php',
        '2024_01_04_000007_create_ptah_permission_audits_table.php',
        '2024_01_05_000000_add_audit_fields_to_ptah_tables.php',
        '2026_03_23_000001_create_ptah_ai_model_configs_table.php',
        '2026_03_23_000002_create_ptah_ai_conversations_table.php',
        '2026_07_20_000000_create_ptah_exports_table.php',
        'ai/2026_03_31_000003_add_user_to_ptah_ai_conversations_table.php',
    ];

    #[Test]
    public function the_package_ships_exactly_the_migrations_it_shipped_at_the_last_release(): void
    {
        $found = self::discover();

        $added = array_values(array_diff($found, self::SHIPPED));
        $removed = array_values(array_diff(self::SHIPPED, $found));

        $this->assertSame(
            [],
            $added,
            "Migration NOVA no pacote:\n  ".implode("\n  ", $added)."\n\n".
            "O schema esta congelado: atualizacao nao pode exigir migration.\n".
            "loadMigrationsFrom faz as migrations do pacote serem auto-descobertas, entao\n".
            "esta vai rodar no proximo `php artisan migrate` que o consumidor executar por\n".
            "motivo dele — sem ter pedido. E quem ja instalou nao vai rodar migrate de novo,\n".
            "logo a capacidade que depende dessa coluna nunca chega nessas instalacoes.\n\n".
            "Alternativas, em ordem: (1) expressar a capacidade como page_object com `read`\n".
            "(padrao de crud.config/ai.config); (2) usar ptah_role_permissions.extra, que ja\n".
            "existe; (3) se nao houver saida, adicionar aqui no manifesto no MESMO commit,\n".
            'com nota de release. Ver o docblock desta classe.'
        );

        $this->assertSame(
            [],
            $removed,
            "Migration REMOVIDA do pacote:\n  ".implode("\n  ", $removed)."\n\n".
            'Instalacao nova depende dela. Remover quebra quem for instalar a partir de agora.'
        );
    }

    /**
     * @return array<int, string>
     */
    private static function discover(): array
    {
        $root = dirname(__DIR__, 3).'/src/Migrations';

        if (! is_dir($root)) {
            throw new RuntimeException('SchemaIsFrozenTest: src/Migrations nao encontrado.');
        }

        $found = [];

        foreach (glob($root.'/*.php') ?: [] as $path) {
            $found[] = basename($path);
        }

        // The ai/ subdirectory is loaded conditionally (ai_agent module), but it is
        // still schema the package ships — it must be pinned the same way.
        foreach (glob($root.'/ai/*.php') ?: [] as $path) {
            $found[] = 'ai/'.basename($path);
        }

        sort($found);

        return $found;
    }
}
