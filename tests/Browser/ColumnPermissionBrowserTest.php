<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as AuthUser;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\RolePermission;
use Ptah\Models\UserRole;

// ── Stub model on the shared `items` table (same table ColumnPermissionScreenTest
//    uses — already migrated by DuskTestCase::ensureDuskDatabaseIsReady()) ──────

class ColumnPermissionDuskStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * The `colsPermission` gate, through a REAL Chrome render — the class of bug
 * no file-reading test (Livewire::test()->html()) can see: whether the
 * denied column's `<th>` genuinely never reaches the DOM, not merely that a
 * string is absent from a server-rendered HTML blob.
 *
 * Overrides `defineWebRoutes()`/`getEnvironmentSetUp()` (rather than editing
 * the shared `DuskTestCase`) — per `server.php`'s "originating test class"
 * mechanism, these overrides apply ONLY when the Dusk server is servicing
 * THIS test class, so every other Browser test is unaffected.
 */
class ColumnPermissionBrowserTest extends DuskTestCase
{
    private const SENSITIVE_AMOUNT = '777888';

    private const DENIED_EMAIL = 'dusk-denied@example.test';

    private const GRANTED_EMAIL = 'dusk-granted@example.test';

    protected function defineWebRoutes($router): void
    {
        parent::defineWebRoutes($router);

        $router->get('/dusk-test/column-permission-crud', function () {
            return view('dusk-crud', ['model' => ColumnPermissionDuskStub::class]);
        });
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app->booted(function (Application $app) {
            $this->ensureColumnPermissionFixtures();
        });
    }

    /**
     * Re-seeded on EVERY request the Dusk server handles (see DuskTestCase's
     * class docblock: each request rebuilds the app from a fresh, empty
     * `:memory:` sqlite) — guarded so a double-boot within the SAME request
     * does not violate a unique constraint.
     */
    private function ensureColumnPermissionFixtures(): void
    {
        if (PageObject::query()->where('obj_key', 'items.secret_amount')->exists()) {
            return;
        }

        CrudConfig::create([
            'model' => ColumnPermissionDuskStub::class,
            'route' => '',
            'config' => [
                'crud' => ColumnPermissionDuskStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    [
                        'colsNomeFisico' => 'amount',
                        'colsNomeLogico' => 'Secret Amount',
                        'colsTipo' => 'number',
                        'colsGravar' => true,
                        'colsPermission' => 'items.secret_amount',
                    ],
                ],
                'permissions' => [],
            ],
        ]);

        ColumnPermissionDuskStub::create(['name' => 'Dusk Alpha', 'status' => 'active', 'amount' => self::SENSITIVE_AMOUNT]);

        $page = PtahPage::create(['slug' => 'dusk-items-screen', 'name' => 'Dusk Items Screen', 'is_active' => true]);
        $object = PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => 'items.secret_amount', 'obj_label' => 'Secret amount',
            'obj_type' => 'field', 'obj_order' => 1, 'is_active' => true,
        ]);

        $deniedRole = Role::create(['name' => 'dusk-denied-role', 'is_master' => false, 'is_active' => true]);
        $grantedRole = Role::create(['name' => 'dusk-granted-role', 'is_master' => false, 'is_active' => true]);

        RolePermission::create([
            'role_id' => $grantedRole->id, 'page_object_id' => $object->id,
            'can_create' => false, 'can_read' => true, 'can_update' => false, 'can_delete' => false,
        ]);

        $deniedUser = $this->makeUser(self::DENIED_EMAIL);
        $grantedUser = $this->makeUser(self::GRANTED_EMAIL);

        UserRole::create(['user_id' => $deniedUser->id, 'role_id' => $deniedRole->id, 'company_id' => null, 'is_active' => true]);
        UserRole::create(['user_id' => $grantedUser->id, 'role_id' => $grantedRole->id, 'company_id' => null, 'is_active' => true]);
    }

    /**
     * Direct property assignment (not `create()`/`fill()`) — the base
     * `Illuminate\Foundation\Auth\User` declares no `$fillable`, so mass
     * assignment would throw; this bypasses that entirely.
     */
    private function makeUser(string $email): AuthUser
    {
        $user = new AuthUser;
        $user->name = $email;
        $user->email = $email;
        $user->password = bcrypt('secret');
        $user->save();

        return $user;
    }

    #[Test]
    public function a_denied_user_never_gets_the_gated_th_or_value_while_a_granted_user_sees_both(): void
    {
        $this->browse(function (Browser $denied, Browser $granted) {
            // `loginAs()` accepts an e-mail (Laravel\Dusk\Http\Controllers\
            // UserController::login() routes it through retrieveByCredentials())
            // — deliberately NOT a numeric ID, since each HTTP request (including
            // the /_dusk/login one) reseeds a brand-new in-memory DB and a
            // cross-process auto-increment ID is not a value this test should
            // depend on lining up.
            $denied->loginAs(self::DENIED_EMAIL)
                ->visit('/dusk-test/column-permission-crud')
                ->waitForText('Dusk Alpha')
                // The public column is unaffected — regression zero. Header
                // text is asserted UPPERCASE: Selenium's getText()/innerText
                // (which Dusk's assertSee wraps) reflects the CSS
                // `text-transform: uppercase` the <th> renders with, not the
                // raw DOM text node.
                ->assertSee('NAME')
                // The gated column's <th> (data-column="amount", see
                // partials/_table.blade.php) must be entirely ABSENT from the
                // DOM — not merely hidden by CSS.
                ->assertMissing('th[data-column="amount"]')
                ->assertDontSee('SECRET AMOUNT')
                ->assertSourceMissing(self::SENSITIVE_AMOUNT);

            $granted->loginAs(self::GRANTED_EMAIL)
                ->visit('/dusk-test/column-permission-crud')
                ->waitForText('Dusk Alpha')
                ->assertPresent('th[data-column="amount"]')
                ->assertSee('SECRET AMOUNT')
                ->assertSee(self::SENSITIVE_AMOUNT);
        });
    }
}
