<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Tests\TestCase;

// ── Stub model on the shared `items` table ──────────────────────────────────

class ColumnPermissionTamperStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount', 'category_id'];
}

class ColumnPermissionTamperUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * Adversarial coverage for the per-column `colsPermission` gate: every
 * client-writable Livewire property that could otherwise be forged to read
 * or infer a denied column's data — sort, filters, advanced search, URL
 * filters, quick-date column, the edit form, formDataColumns, groupBreak,
 * totalizadores, contitionStyles and the link/custom-method renderers.
 *
 * A single sensitive column ('amount', tagged `items.secret_amount`) is
 * used throughout; the acting user is assigned a role with NO grant on it
 * (denied) unless a test says otherwise.
 */
class ColumnPermissionTamperTest extends TestCase
{
    // Deliberately unusual, wide 6-digit numbers — a short/common value like
    // "300" collides with Tailwind utility classes (duration-300, etc.) and
    // other page chrome rendered by every request, making assertDontSee()
    // pass or fail for the wrong reason.
    private const AMOUNT_ALPHA = 784511;

    private const AMOUNT_BETA = 912233;

    private const AMOUNT_CHARLIE = 650077;

    private function makeConfig(array $extra = []): void
    {
        CrudConfig::create([
            'model' => ColumnPermissionTamperStub::class,
            'route' => '',
            'config' => array_merge([
                'crud' => ColumnPermissionTamperStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => 'S'],
                    [
                        'colsNomeFisico' => 'amount',
                        'colsNomeLogico' => 'Secret Amount',
                        'colsTipo' => 'number',
                        'colsGravar' => true,
                        'colsIsFilterable' => 'S',
                        'colsPermission' => 'items.secret_amount',
                    ],
                ],
                'permissions' => [],
            ], $extra),
        ]);
    }

    private function seedRows(): array
    {
        $alpha = ColumnPermissionTamperStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => self::AMOUNT_ALPHA]);
        $beta = ColumnPermissionTamperStub::create(['name' => 'Beta', 'status' => 'active', 'amount' => self::AMOUNT_BETA]);
        $charlie = ColumnPermissionTamperStub::create(['name' => 'Charlie', 'status' => 'active', 'amount' => self::AMOUNT_CHARLIE]);

        return [$alpha, $beta, $charlie];
    }

    private function actingAsUser(): ColumnPermissionTamperUser
    {
        $user = ColumnPermissionTamperUser::create([
            'name' => 'Tester',
            'email' => 'tester'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    private function makePage(string $slug): PtahPage
    {
        return PtahPage::create(['slug' => $slug, 'name' => $slug, 'is_active' => true]);
    }

    private function makeObject(PtahPage $page, string $key): PageObject
    {
        return PageObject::create([
            'page_id' => $page->id, 'section' => 'main',
            'obj_key' => $key, 'obj_label' => $key,
            'obj_type' => 'button', 'obj_order' => 1, 'is_active' => true,
        ]);
    }

    private function makeRole(): Role
    {
        return Role::create(['name' => 'R'.uniqid(), 'is_master' => false, 'is_active' => true]);
    }

    /**
     * Seeds a page/object for `items.secret_amount` and assigns the acting
     * user a role with NO grant on it — the denied path exercised by every
     * test in this file.
     */
    private function denyCurrentUser(int $userId): void
    {
        $page = $this->makePage('items-screen-'.uniqid());
        $this->makeObject($page, 'items.secret_amount');
        UserRole::create(['user_id' => $userId, 'role_id' => $this->makeRole()->id, 'company_id' => null, 'is_active' => true]);
    }

    // ── (1) Forged sort falls back to id ─────────────────────────────────────

    #[Test]
    public function a_forged_sort_on_the_denied_column_falls_back_to_id(): void
    {
        $this->makeConfig();
        [$alpha, $beta, $charlie] = $this->seedRows(); // amounts: Alpha 784511, Beta 912233, Charlie 650077 — ids 1,2,3
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        // direction defaults to DESC — sorting by amount would read
        // Beta(912233), Alpha(784511), Charlie(650077); falling back to id
        // gives Charlie(3), Beta(2), Alpha(1) instead. The two sequences
        // differ, so the assertion below only holds if the fallback fired.
        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->set('sort', 'amount')
            ->assertSeeInOrder(['Charlie', 'Beta', 'Alpha']);

        unset($alpha, $beta, $charlie);
    }

    // ── (2) Forged panel filter has no effect ────────────────────────────────

    #[Test]
    public function a_forged_panel_filter_on_the_denied_column_has_no_effect(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->set('filters.amount', 999) // would match nothing if applied
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Charlie');
    }

    // ── (3) Forged advanced-search field is ignored ──────────────────────────

    #[Test]
    public function a_forged_advanced_search_field_on_the_denied_column_is_ignored(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->set('advancedSearchActive', true)
            // Threshold sits strictly between Alpha and Beta — would keep
            // only Beta (912233) if the forged field were actually applied.
            ->call('addAdvancedSearchField', 'amount', '>', self::AMOUNT_ALPHA + 1)
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Charlie');
    }

    // ── (4) Forged URL filter is ignored, no SQL error ───────────────────────

    #[Test]
    public function a_forged_url_filter_on_the_denied_column_is_ignored_without_a_sql_error(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        Livewire::withQueryParams(['f' => ['amount' => 100]])
            ->test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->assertOk()
            ->assertSet('urlFilters', [])
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Charlie');
    }

    // ── (5) Forged quick-date column is ignored ──────────────────────────────

    #[Test]
    public function a_forged_quick_date_column_on_the_denied_column_is_ignored(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->set('quickDateColumn', 'amount')
            ->call('applyQuickDateFilter', 'today')
            ->assertOk()
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Charlie');
    }

    // ── (6) openEdit() never carries the denied field ────────────────────────

    #[Test]
    public function open_edit_never_exposes_the_denied_column_in_form_data(): void
    {
        $this->makeConfig();
        [$alpha] = $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->call('openEdit', $alpha->id)
            ->assertNotSet('formData.amount', self::AMOUNT_ALPHA)
            ->assertSet('formData', fn (array $data) => ! array_key_exists('amount', $data));
    }

    // ── (7) A forged save() on the denied field never persists ──────────────

    #[Test]
    public function a_forged_form_data_write_on_the_denied_column_never_persists(): void
    {
        $this->makeConfig();
        [$alpha] = $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->call('openEdit', $alpha->id)
            ->set('formData.name', 'Alpha Updated')
            ->set('formData.amount', 999999) // forged — must never reach the DB
            ->call('save');

        $this->assertSame('Alpha Updated', $alpha->refresh()->name);
        $this->assertSame(self::AMOUNT_ALPHA, $alpha->refresh()->amount);
    }

    // ── (8) Forging formDataColumns cannot resurrect the column ──────────────

    #[Test]
    public function forging_formdatacolumns_cannot_resurrect_the_denied_column(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->set('formDataColumns.amount', true)
            ->assertDontSee('Secret Amount')
            ->assertDontSee((string) self::AMOUNT_ALPHA);
    }

    // ── (9) groupBreak on the denied column renders no group headers ────────

    #[Test]
    public function a_denied_groupbreak_column_renders_no_group_header_or_value(): void
    {
        $this->makeConfig(['groupBreak' => 'amount']);
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Charlie')
            ->assertDontSee('Secret Amount')
            ->assertDontSee((string) self::AMOUNT_ALPHA)
            ->assertDontSee((string) self::AMOUNT_BETA)
            ->assertDontSee((string) self::AMOUNT_CHARLIE);
    }

    // ── (10) totalizador on the denied column is absent ──────────────────────

    #[Test]
    public function a_denied_totalizador_column_is_absent_from_the_footer(): void
    {
        $this->makeConfig([
            'totalizadores' => [
                'enabled' => true,
                'columns' => [
                    ['field' => 'amount', 'aggregate' => 'sum'],
                ],
            ],
        ]);
        $this->seedRows(); // sum would be 2346821 if the totalizador leaked through
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        $sum = self::AMOUNT_ALPHA + self::AMOUNT_BETA + self::AMOUNT_CHARLIE;

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->assertDontSee((string) $sum);
    }

    // ── (11) contitionStyle on the denied column never applies ───────────────

    #[Test]
    public function a_denied_contitionstyle_rule_never_applies(): void
    {
        $this->makeConfig([
            'contitionStyles' => [
                // value=0 matches every seeded row — proves the rule would
                // visibly apply to AT LEAST one row if it leaked through.
                ['field' => 'amount', 'condition' => '>', 'value' => 0, 'style' => 'background-color:#ff00aa'],
            ],
        ]);
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->assertSee('Alpha')
            ->assertDontSee('#ff00aa');
    }

    // ── (12) renderLink never substitutes the denied column's value ─────────

    #[Test]
    public function render_link_never_substitutes_the_denied_columns_value(): void
    {
        $this->makeConfig([
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                [
                    'colsNomeFisico' => 'name',
                    'colsNomeLogico' => 'Name',
                    'colsTipo' => 'text',
                    'colsGravar' => true,
                    'colsRenderer' => 'link',
                    'colsRendererLinkTemplate' => '/view/%id%/%amount%',
                ],
                [
                    'colsNomeFisico' => 'amount',
                    'colsNomeLogico' => 'Secret Amount',
                    'colsTipo' => 'number',
                    'colsGravar' => true,
                    'colsPermission' => 'items.secret_amount',
                ],
            ],
        ]);
        $this->seedRows(); // Alpha's real amount must never appear in the href
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->assertSee('Alpha')
            ->assertDontSee('/view/1/'.self::AMOUNT_ALPHA)
            ->assertDontSee((string) self::AMOUNT_ALPHA);
    }

    // ── (13) Quick search cannot probe a denied column ───────────────────────

    #[Test]
    public function quick_search_by_a_known_denied_value_does_not_find_the_row(): void
    {
        // Review scenario C: the protection is structural (the global search
        // derives from the already-filtered cols) — this pins it so a future
        // change reading cols from another source cannot reopen probing.
        $this->makeConfig([
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true, 'colsSearchable' => true],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Secret Amount', 'colsTipo' => 'number', 'colsGravar' => true, 'colsSearchable' => true, 'colsPermission' => 'items.secret_amount'],
            ],
        ]);
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->set('search', (string) self::AMOUNT_ALPHA)
            ->assertDontSee('Alpha');
    }

    // ── (14) customFilters over a denied column leave the config ─────────────

    #[Test]
    public function a_custom_filter_pointing_at_a_denied_column_is_stripped_from_the_config(): void
    {
        $this->makeConfig([
            'customFilters' => [
                ['field' => 'amount', 'label' => 'Secret range', 'op' => '>', 'value' => 0],
                ['field' => 'name', 'label' => 'Name filter', 'op' => 'like', 'value' => ''],
            ],
        ]);
        $this->seedRows();
        $user = $this->actingAsUser();
        $this->denyCurrentUser($user->id);

        $config = Livewire::test(BaseCrud::class, ['model' => ColumnPermissionTamperStub::class])
            ->get('crudConfig');

        $fields = array_column($config['customFilters'] ?? [], 'field');
        $this->assertNotContains('amount', $fields, 'The denied-column customFilter must be stripped with the column.');
        $this->assertContains('name', $fields, 'Public customFilters must survive.');
    }
}
