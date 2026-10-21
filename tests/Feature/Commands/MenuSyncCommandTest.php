<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Menu;
use Ptah\Tests\TestCase;

/**
 * Covers ptah:menu-sync — MenuRegistry.php → menus table, including
 * idempotency (re-running updates instead of duplicating) and --fresh.
 */
class MenuSyncCommandTest extends TestCase
{
    private string $tmpPath;

    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->tmpPath = sys_get_temp_dir().'/ptah-menu-'.uniqid();
        $this->app->useDatabasePath($this->tmpPath.'/database');
        $this->files->ensureDirectoryExists($this->tmpPath.'/database/seeders');
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmpPath);
        parent::tearDown();
    }

    private function writeRegistry(): void
    {
        $this->files->put($this->tmpPath.'/database/seeders/MenuRegistry.php', <<<'PHP'
<?php

return [
    'links' => [
        ['text' => 'Tasks', 'url' => '/tasks', 'icon' => 'bx bx-task', 'order' => 1],
    ],
    'groups' => [
        'catalog' => [
            'text' => 'Catalog',
            'icon' => 'bx bx-store',
            'order' => 2,
            'links' => [
                ['text' => 'Products', 'url' => '/products', 'icon' => 'bx bx-box', 'order' => 1],
                ['text' => 'Categories', 'url' => '/categories', 'icon' => 'bx bx-tag', 'order' => 2],
            ],
        ],
    ],
];
PHP);
    }

    #[Test]
    public function it_fails_when_the_registry_is_missing(): void
    {
        $this->artisan('ptah:menu-sync')->assertFailed();
    }

    #[Test]
    public function it_syncs_flat_links_groups_and_children(): void
    {
        $this->writeRegistry();

        $this->artisan('ptah:menu-sync')->assertSuccessful();

        // Flat link at root
        $flat = DB::table('menus')->where('url', '/tasks')->whereNull('parent_id')->first();
        $this->assertNotNull($flat);
        $this->assertSame('menuLink', $flat->type);

        // Group with two children
        $group = DB::table('menus')->where('type', 'menuGroup')->where('text', 'Catalog')->first();
        $this->assertNotNull($group);

        $children = DB::table('menus')->where('parent_id', $group->id)->orderBy('link_order')->get();
        $this->assertCount(2, $children);
        $this->assertSame('/products', $children[0]->url);
        $this->assertSame('/categories', $children[1]->url);
    }

    #[Test]
    public function re_running_updates_instead_of_duplicating(): void
    {
        $this->writeRegistry();

        $this->artisan('ptah:menu-sync')->assertSuccessful();
        $countAfterFirst = DB::table('menus')->count();

        $this->artisan('ptah:menu-sync')->assertSuccessful();

        $this->assertSame(
            $countAfterFirst,
            DB::table('menus')->count(),
            'Second sync must update existing rows, never duplicate them',
        );
    }

    #[Test]
    public function fresh_clears_stale_rows_before_syncing(): void
    {
        $this->writeRegistry();

        // A stale menu row no longer present in the registry.
        DB::table('menus')->insert([
            'parent_id' => null,
            'text' => 'Stale entry',
            'url' => '/stale',
            'type' => 'menuLink',
            'target' => '_self',
            'link_order' => 99,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('ptah:menu-sync', ['--fresh' => true])
            ->expectsConfirmation('--fresh DELETES every menu item, including the ones created in the menu editor. Prefer --prune. Continue?', 'yes')
            ->assertSuccessful();

        $this->assertNull(
            DB::table('menus')->where('url', '/stale')->first(),
            '--fresh must remove rows that are not in the registry',
        );
        $this->assertNotNull(DB::table('menus')->where('url', '/tasks')->first());
    }

    private function insertStale(string $text, ?string $url, string $type = 'menuLink'): int
    {
        return DB::table('menus')->insertGetId([
            'parent_id' => null, 'text' => $text, 'url' => $url, 'type' => $type,
            'target' => '_self', 'link_order' => 99, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** 1.46.5 (Engepecas): sem --prune, a unica saida para item orfao era o --fresh. */
    #[Test]
    public function prune_deactivates_what_left_the_registry_and_never_deletes(): void
    {
        $this->writeRegistry();
        $this->artisan('ptah:menu-sync')->assertSuccessful();
        $link = $this->insertStale('Old link', '/old');
        $group = $this->insertStale('Old group', null, 'menuGroup');
        $before = DB::table('menus')->count();

        $this->artisan('ptah:menu-sync', ['--prune' => true])
            ->expectsOutputToContain('Old link')
            ->assertSuccessful();

        $this->assertSame($before, DB::table('menus')->count(), '--prune nunca apaga linha.');
        $this->assertSame(0, (int) DB::table('menus')->where('id', $link)->value('is_active'));
        $this->assertSame(0, (int) DB::table('menus')->where('id', $group)->value('is_active'));
        $this->assertSame(0, DB::table('menus')->whereIn('url', ['/tasks', '/products', '/categories'])->where('is_active', false)->count());
        $this->assertSame(1, (int) DB::table('menus')->where('type', 'menuGroup')->where('text', 'Catalog')->value('is_active'));
    }

    #[Test]
    public function prune_dry_run_lists_without_writing(): void
    {
        $this->writeRegistry();
        $this->artisan('ptah:menu-sync')->assertSuccessful();
        $link = $this->insertStale('Old link', '/old');

        $this->artisan('ptah:menu-sync', ['--prune' => true, '--dry-run' => true])
            ->expectsOutputToContain('Old link')
            ->assertSuccessful();

        $this->assertSame(1, (int) DB::table('menus')->where('id', $link)->value('is_active'));
        $this->assertSame(0, DB::table('menus')->where('is_active', false)->count());
    }

    #[Test]
    public function sync_and_model_changes_clear_the_sidebar_cache(): void
    {
        $this->writeRegistry();

        Cache::put('ptah_menu_tree', ['stale'], 300);
        $this->artisan('ptah:menu-sync')->assertSuccessful();
        $this->assertFalse(Cache::has('ptah_menu_tree'), 'O sync grava sem eventos; tem de limpar o cache.');

        Cache::put('ptah_menu_tree', ['stale'], 300);
        Menu::query()->where('url', '/tasks')->first()->update(['text' => 'Tarefas']);
        $this->assertFalse(Cache::has('ptah_menu_tree'), 'Mudanca pelo model invalida o cache.');

        Cache::put('ptah_menu_tree', ['stale'], 300);
        $this->artisan('ptah:menu-cache-clear')->assertSuccessful();
        $this->assertFalse(Cache::has('ptah_menu_tree'));
    }

    /** 1.50.0: --fresh deleted the whole table without asking. */
    #[Test]
    public function fresh_asks_first_and_a_no_deletes_nothing(): void
    {
        $this->writeRegistry();
        $id = $this->insertStale('Editor item', '/editor');

        $this->artisan('ptah:menu-sync', ['--fresh' => true])
            ->expectsConfirmation('--fresh DELETES every menu item, including the ones created in the menu editor. Prefer --prune. Continue?', 'no')
            ->assertFailed();

        $this->assertNotNull(DB::table('menus')->find($id));
        $this->artisan('ptah:menu-sync', ['--fresh' => true, '--force' => true])->assertSuccessful();
        // By url, not id: SQLite reuses the highest rowid after a delete.
        $this->assertSame(0, DB::table('menus')->where('url', '/editor')->count());
    }
}
