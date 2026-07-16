<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Models\UserPreference;
use Ptah\Tests\TestCase;

// ── Stub model on the `items` table ──────────────────────────────────────────

class UrlFilterStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount', 'category_id'];

    /** Self-referencing relation — only exercised by the searchdropdown/relation-column test. */
    public function category(): BelongsTo
    {
        return $this->belongsTo(self::class, 'category_id');
    }
}

class UrlFilterCrudUser extends Authenticatable
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * Covers URL filters (?f[field]=value) through the real BaseCrud component:
 * simple/explicit-operator/IN formats, the field whitelist, precedence over
 * saved preferences, the panel discarding them, locked filters staying
 * immune, and that they are never written to preferences.
 */
class CrudUrlFiltersTest extends TestCase
{
    private function makeConfig(array $extra = []): void
    {
        CrudConfig::create([
            'model' => UrlFilterStub::class,
            'route' => '',
            'config' => array_merge([
                'crud' => UrlFilterStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => 'S'],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => 'S'],
                    // Not filterable on purpose (no colsIsFilterable) — used to
                    // prove non-filterable columns are rejected via URL too.
                    ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Amount', 'colsTipo' => 'number', 'colsGravar' => true],
                    // Plain numeric "_id"-suffixed column WITHOUT colsRelacao — used
                    // to prove BETWEEN isn't misclassified as a relation filter by
                    // the "_id" naming heuristic (FilterDTO::inferType()).
                    ['colsNomeFisico' => 'category_id', 'colsNomeLogico' => 'Category', 'colsTipo' => 'number', 'colsGravar' => true, 'colsIsFilterable' => 'S'],
                ],
                'permissions' => [],
            ], $extra),
        ]);
    }

    private function seedRows(): void
    {
        UrlFilterStub::create(['name' => 'Alpha', 'status' => 'active', 'amount' => 10, 'category_id' => 5]);
        UrlFilterStub::create(['name' => 'Beta', 'status' => 'inactive', 'amount' => 20, 'category_id' => 15]);
        UrlFilterStub::create(['name' => 'Charlie', 'status' => 'pending', 'amount' => 30, 'category_id' => 25]);
    }

    private function actingAsUser(): UrlFilterCrudUser
    {
        $user = UrlFilterCrudUser::create([
            'name' => 'Tester',
            'email' => 'tester'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->actingAs($user);

        return $user;
    }

    // ── (a) Simple filter ────────────────────────────────────────────────────

    #[Test]
    public function a_simple_url_filter_is_applied(): void
    {
        $this->makeConfig();
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['status' => 'active']])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('urlFilters', ['status' => ['op' => '=', 'val' => 'active']])
            ->assertSee('Alpha')
            ->assertDontSee('Beta')
            ->assertDontSee('Charlie')
            // Banner renders with its heading and a chip showing the column
            // label ("Status") followed by the value ("active").
            ->assertSee(__('ptah::ui.url_filters_active'))
            ->assertSeeInOrder(['Status', 'active']);
    }

    // ── (b) Explicit operator ────────────────────────────────────────────────

    #[Test]
    public function an_explicit_operator_is_honoured(): void
    {
        $this->makeConfig();
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['name' => ['op' => 'LIKE', 'val' => 'lph']]])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('urlFilters', ['name' => ['op' => 'LIKE', 'val' => 'lph']])
            ->assertSee('Alpha')
            ->assertDontSee('Beta')
            ->assertDontSee('Charlie');
    }

    #[Test]
    public function an_operator_outside_the_allowlist_falls_back_to_equals(): void
    {
        $this->makeConfig();
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['status' => ['op' => 'DROP TABLE', 'val' => 'active']]])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('urlFilters', ['status' => ['op' => '=', 'val' => 'active']])
            ->assertSee('Alpha')
            ->assertDontSee('Beta');
    }

    // ── (c) List → IN ─────────────────────────────────────────────────────────

    #[Test]
    public function a_list_of_values_becomes_an_in_filter(): void
    {
        $this->makeConfig();
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['status' => ['active', 'pending']]])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('urlFilters', ['status' => ['op' => 'IN', 'val' => ['active', 'pending']]])
            ->assertSee('Alpha')
            ->assertSee('Charlie')
            ->assertDontSee('Beta');
    }

    // ── (d) Whitelist ─────────────────────────────────────────────────────────

    #[Test]
    public function a_field_outside_the_whitelist_is_silently_ignored(): void
    {
        $this->makeConfig();
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['not_a_column' => 'x']])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('urlFilters', [])
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Charlie');
    }

    // ── (e) URL overrides saved preferences ───────────────────────────────────

    #[Test]
    public function url_filters_override_saved_preferences(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $this->actingAsUser();

        // First visit (no URL filter): apply a panel filter → persisted as a preference.
        Livewire::test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->set('filters.status', 'inactive');

        // Second visit, WITH ?f[status]=active — the preference still loads into
        // $filters, but the URL value must be the one that actually filters rows.
        Livewire::withQueryParams(['f' => ['status' => 'active']])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('filters.status', 'inactive')
            ->assertSee('Alpha')
            ->assertDontSee('Beta');
    }

    // ── (f) Touching the panel clears URL filters ─────────────────────────────

    #[Test]
    public function applying_a_panel_filter_discards_the_url_filters(): void
    {
        $this->makeConfig();
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['status' => 'active']])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('urlFilters', ['status' => ['op' => '=', 'val' => 'active']])
            ->set('filters.name', 'Beta')
            ->assertSet('urlFilters', []);
    }

    #[Test]
    public function clear_url_filters_empties_the_state(): void
    {
        $this->makeConfig();
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['status' => 'active']])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('urlFilters', ['status' => ['op' => '=', 'val' => 'active']])
            ->call('clearUrlFilters')
            ->assertSet('urlFilters', [])
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Charlie');
    }

    // ── (g) Locked filters cannot be escaped ──────────────────────────────────

    #[Test]
    public function locked_filters_are_kept_even_with_a_forged_url_filter_on_the_same_field(): void
    {
        $this->makeConfig();
        $this->seedRows();

        // The locked scope restricts to "active" (Alpha). The forged URL filter
        // tries a permissive "status != none" (true for every row) on the SAME
        // locked field — if the lock could be bypassed, Beta/Charlie would leak.
        Livewire::withQueryParams(['f' => ['status' => ['op' => '!=', 'val' => 'none']]])
            ->test(BaseCrud::class, [
                'model' => UrlFilterStub::class,
                'lockedFilters' => ['status' => 'active'],
            ])
            ->assertSee('Alpha')
            ->assertDontSee('Beta')
            ->assertDontSee('Charlie');
    }

    // ── (h) Never persisted ────────────────────────────────────────────────────

    #[Test]
    public function url_filters_are_never_persisted(): void
    {
        $this->makeConfig();
        $this->seedRows();
        $user = $this->actingAsUser();

        Livewire::withQueryParams(['f' => ['status' => 'active']])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('urlFilters', ['status' => ['op' => '=', 'val' => 'active']])
            ->call('sortBy', 'name'); // triggers savePreferences()

        $stored = UserPreference::get($user->id, 'crud.'.UrlFilterStub::class, null);
        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('urlFilters', $stored);

        // A fresh mount WITHOUT query params must not resurrect the URL filter.
        // (withQueryParams([]) resets Livewire's test-only query params — they
        // otherwise bleed into the next ->test() call within the same method.)
        Livewire::withQueryParams([])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('urlFilters', []);
    }

    // ── CRITICAL #1 — urlFilters must be #[Locked] against client mutation ────
    // Without #[Locked], captureUrlFilters()'s whitelist only guards mount();
    // any later Livewire request could rewrite the property directly (e.g.
    // ->set('urlFilters', [...])) and smuggle a field/operator that never went
    // through allowedFilterFields()/the operator allowlist.

    #[Test]
    public function client_cannot_mutate_url_filters_directly(): void
    {
        $this->makeConfig();
        $this->seedRows();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->set('urlFilters', ['created_at' => ['op' => '>', 'val' => '1970-01-01']]);
    }

    #[Test]
    public function server_side_writers_still_work_despite_the_lock(): void
    {
        // #[Locked] only rejects CLIENT updates — captureUrlFilters() (mount())
        // and clearUrlFilters()/the precedence resets (server-side PHP) must be
        // unaffected.
        $this->makeConfig();
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['status' => 'active']])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('urlFilters', ['status' => ['op' => '=', 'val' => 'active']])
            ->call('clearUrlFilters')
            ->assertSet('urlFilters', []);
    }

    // ── CRITICAL #2 — precedence must hold against a colliding customFilter ───

    #[Test]
    public function url_filter_overrides_a_colliding_custom_filter_preference(): void
    {
        $this->makeConfig([
            'customFilters' => [
                ['field' => 'status', 'operator' => '='],
            ],
        ]);
        $this->seedRows();
        $this->actingAsUser();

        // Persist a panel value for the SAME field the customFilter reads.
        Livewire::test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->set('filters.status', 'inactive');

        // ?f[status]=active must win outright — NOT AND with the stale
        // 'inactive' preference (which would otherwise yield 0 rows:
        // status='inactive' AND status='active').
        Livewire::withQueryParams(['f' => ['status' => 'active']])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSee('Alpha')
            ->assertDontSee('Beta')
            ->assertDontSee('Charlie');
    }

    // ── CRITICAL #3 — a smuggled nested array must never crash the render ─────

    #[Test]
    public function a_nested_array_in_the_op_val_format_is_discarded_without_crashing(): void
    {
        $this->makeConfig();
        $this->seedRows();

        // ?f[name][val][][sub]=1 parses into a nested array as the value. It
        // must be discarded (not reach whereIn()/whereBetween() or the
        // banner's implode()), and the request must render normally.
        Livewire::withQueryParams(['f' => ['name' => ['val' => [['sub' => '1']]]]])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertOk()
            ->assertSet('urlFilters', [])
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Charlie');
    }

    #[Test]
    public function a_smuggled_non_scalar_list_item_is_dropped_without_crashing(): void
    {
        $this->makeConfig();
        $this->seedRows();

        // ?f[status][]=active&f[status][][sub]=1 — the plain-list format drops
        // the non-scalar item individually; the rest of the list still filters.
        Livewire::withQueryParams(['f' => ['status' => ['active', ['sub' => '1']]]])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertOk()
            ->assertSet('urlFilters', ['status' => ['op' => 'IN', 'val' => ['active']]])
            ->assertSee('Alpha')
            ->assertDontSee('Beta');
    }

    // ── ATTENTION #4 — colsIsFilterable must be honoured by the whitelist ──────

    #[Test]
    public function a_non_filterable_column_is_rejected_even_though_it_exists(): void
    {
        // 'amount' exists in cols but carries no colsIsFilterable flag.
        $this->makeConfig();
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['amount' => '10']])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertSet('urlFilters', [])
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Charlie');
    }

    // ── ATTENTION #5 — BETWEEN on a plain "_id" column must stay numeric ──────

    #[Test]
    public function between_on_a_plain_id_column_is_not_misclassified_as_a_relation_filter(): void
    {
        // category_id is a plain numeric column (colsTipo=number) WITHOUT
        // colsRelacao. FilterDTO::inferType()'s naming heuristic alone would
        // still tag it 'relation' (any "_id" suffix), sending BETWEEN through
        // RelationFilterStrategy — which doesn't support it and silently
        // downgrades to '='. Resolving the type from the real column config
        // must keep it numeric so the [from, to] range actually applies.
        $this->makeConfig();
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['category_id' => ['op' => 'BETWEEN', 'val' => [1, 20]]]])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertOk()
            ->assertSee('Alpha')   // category_id = 5  → inside [1, 20]
            ->assertSee('Beta')    // category_id = 15 → inside [1, 20]
            ->assertDontSee('Charlie'); // category_id = 25 → outside [1, 20]
    }

    // ── ATTENTION #5 (follow-up) — relation columns (searchdropdown/select) ───
    // colsTipo alone is NOT a reliable signal — the docs (Configuration.md)
    // document category_id as colsTipo="searchdropdown" (not "number") with
    // colsRelacao/colsRelacaoExibe marking the relation. Any colsTipo outside
    // number/date/datetime/boolean used to fall to 'text', so BETWEEN on this
    // very realistic column shape resolved to TextFilterStrategy's default
    // where() — which silently rewrites the invalid 'BETWEEN' operator into
    // `category_id = 'BETWEEN'` (0 rows, no exception).

    #[Test]
    public function between_on_a_searchdropdown_relation_column_operates_on_the_raw_fk_id(): void
    {
        CrudConfig::create([
            'model' => UrlFilterStub::class,
            'route' => '',
            'config' => [
                'crud' => UrlFilterStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true, 'colsIsFilterable' => 'S'],
                    [
                        'colsNomeFisico' => 'category_id',
                        'colsNomeLogico' => 'Category',
                        'colsTipo' => 'searchdropdown',
                        'colsRelacao' => 'category',
                        'colsRelacaoExibe' => 'name',
                        'colsGravar' => true,
                        'colsIsFilterable' => 'S',
                    ],
                ],
                'permissions' => [],
            ],
        ]);
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['category_id' => ['op' => 'BETWEEN', 'val' => [1, 20]]]])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertOk()
            ->assertSee('Alpha')   // category_id = 5  → inside [1, 20]
            ->assertSee('Beta')    // category_id = 15 → inside [1, 20]
            ->assertDontSee('Charlie'); // category_id = 25 → outside [1, 20]
    }

    #[Test]
    public function between_on_a_genuinely_textual_column_is_discarded_not_silently_wrong(): void
    {
        // 'name' resolves to type 'text' (colsTipo=text, no colsRelacao).
        // BETWEEN has no TextFilterStrategy implementation — rather than let
        // it silently rewrite into `where('name', '=', 'BETWEEN')`, the filter
        // must be dropped entirely: the request renders fine and every row
        // still shows, exactly as if the filter had never been given.
        $this->makeConfig();
        $this->seedRows();

        Livewire::withQueryParams(['f' => ['name' => ['op' => 'BETWEEN', 'val' => ['Alpha', 'Zeta']]]])
            ->test(BaseCrud::class, ['model' => UrlFilterStub::class])
            ->assertOk()
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertSee('Charlie');
    }
}
