<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
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

        $this->artisan('ptah:menu-sync', ['--fresh' => true])->assertSuccessful();

        $this->assertNull(
            DB::table('menus')->where('url', '/stale')->first(),
            '--fresh must remove rows that are not in the registry',
        );
        $this->assertNotNull(DB::table('menus')->where('url', '/tasks')->first());
    }
}
