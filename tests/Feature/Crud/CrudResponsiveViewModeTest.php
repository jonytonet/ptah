<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class ResponsiveViewStub extends Model
{
    protected $table = 'bulk_action_stubs';

    protected $fillable = ['name', 'status'];
}

/**
 * The card view existed but was never the default on a phone — you had to find
 * the toolbar and pick it — and once picked it could not be ordered: the sort
 * came from whatever the TABLE view had last chosen, which on a device where
 * the table is never opened means "always id DESC". Both reported from a
 * production ERP running on ptah.
 *
 * `viewMode` gained a third state, 'auto', because the property is a PERSISTED
 * per-user preference while the layout question is per-DEVICE: writing 'cards'
 * because someone opened the screen on a phone would hand their desktop session
 * a card grid the next morning. 'auto' stores nothing device-specific and lets
 * CSS decide at render time.
 */
class CrudResponsiveViewModeTest extends TestCase
{
    private function makeConfig(): void
    {
        CrudConfig::create([
            'model' => ResponsiveViewStub::class,
            'route' => '',
            'config' => [
                'crud' => ResponsiveViewStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => [],
            ],
        ]);
    }

    /**
     * The unauthenticated fallback path of savePreferences() writes to
     * `session('ptah.crud.'.$model)`. That key is DOTTED, so Laravel stores it
     * as a nested array — `session()->all()` shows only the top-level 'ptah',
     * which is why walking the session keys finds nothing. Read it with dot
     * notation instead.
     */
    private function preferenceKey(): string
    {
        return 'ptah.crud.'.ResponsiveViewStub::class;
    }

    /**
     * Rewrites the persisted blob as a pre-'auto' installation would have left
     * it: same values, older schema version.
     */
    private function ageThePreferenceBlob(string $viewMode): void
    {
        $key = $this->preferenceKey();
        $prefs = session($key);

        $this->assertIsArray($prefs, 'savePreferences() nao gravou na sessao — o caminho nao autenticado mudou?');

        $prefs['_version'] = '2.1.0';
        $prefs['viewMode'] = $viewMode;

        session([$key => $prefs]);
    }

    // ── The default ─────────────────────────────────────────────────────────

    #[Test]
    public function a_fresh_screen_defaults_to_auto(): void
    {
        $this->makeConfig();

        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->assertSet('viewMode', 'auto');
    }

    #[Test]
    public function auto_renders_both_layouts_each_gated_by_its_own_breakpoint(): void
    {
        $this->makeConfig();

        // The server cannot know the viewport, so 'auto' emits both and lets CSS
        // choose. Asserting the gates (not just "sees a table") is the point:
        // without them the phone would get both stacked.
        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->assertSeeHtml('hidden md:block')
            ->assertSeeHtml('md:hidden');
    }

    #[Test]
    public function pinning_a_mode_renders_only_that_one(): void
    {
        $this->makeConfig();

        // A pin is also what keeps the payload down: no second layout is emitted.
        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->call('setViewMode', 'cards')
            ->assertDontSeeHtml('hidden md:block');

        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->call('setViewMode', 'table')
            ->assertDontSeeHtml('md:hidden');
    }

    #[Test]
    public function an_unknown_mode_is_rejected(): void
    {
        $this->makeConfig();

        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->call('setViewMode', 'carousel')
            ->assertSet('viewMode', 'auto');
    }

    #[Test]
    public function disabling_responsive_cards_makes_auto_behave_like_table(): void
    {
        config(['ptah.crud.responsive_cards' => false]);
        $this->makeConfig();

        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->assertSet('viewMode', 'auto')
            ->assertDontSeeHtml('md:hidden');
    }

    // ── The legacy migration ────────────────────────────────────────────────

    #[Test]
    public function a_pre_2_2_0_table_preference_is_read_as_auto(): void
    {
        $this->makeConfig();

        // Every installation that predates 'auto' has 'table' persisted because
        // it was the DEFAULT, not a choice. Reading those as a deliberate pin
        // would mean the responsive layout never reaches a single existing user
        // — precisely the people who reported the problem.
        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->call('setViewMode', 'table');

        $this->ageThePreferenceBlob('table');

        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->assertSet('viewMode', 'auto');
    }

    #[Test]
    public function a_table_pin_chosen_after_the_upgrade_is_respected(): void
    {
        $this->makeConfig();

        // The version marker is what separates "the old default" from "what the
        // user just picked". Without it the migration would keep overriding a
        // deliberate choice on every page load.
        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->call('setViewMode', 'table');

        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->assertSet('viewMode', 'table');
    }

    #[Test]
    public function a_legacy_cards_preference_is_never_touched(): void
    {
        $this->makeConfig();

        // 'cards' in an old blob WAS deliberate — nothing defaulted to it.
        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->call('setViewMode', 'cards');

        $this->ageThePreferenceBlob('cards');

        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->assertSet('viewMode', 'cards');
    }

    // ── Sorting in the card view ────────────────────────────────────────────

    #[Test]
    public function the_card_view_renders_a_sort_control(): void
    {
        $this->makeConfig();

        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->call('setViewMode', 'cards')
            ->assertSeeHtml('ptah-c-sortbar')
            ->assertSeeHtml('toggleSortDirection');
    }

    #[Test]
    public function the_sort_select_offers_the_same_columns_the_table_headers_do(): void
    {
        $this->makeConfig();

        $component = Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class]);

        $offered = array_column($component->instance()->sortableColumns(), 'field');

        // Both the clickable headers and the select read sortableColumns(), so
        // this pins the contract rather than the current column list.
        $this->assertContains('name', $offered);
        $this->assertContains('status', $offered);
    }

    #[Test]
    public function toggling_the_direction_flips_it_and_nothing_else(): void
    {
        $this->makeConfig();

        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->assertSet('direction', 'DESC')
            ->call('toggleSortDirection')
            ->assertSet('direction', 'ASC')
            ->assertSet('sort', 'id')
            ->call('toggleSortDirection')
            ->assertSet('direction', 'DESC');
    }

    #[Test]
    public function choosing_a_column_in_the_select_does_not_reverse_the_direction(): void
    {
        $this->makeConfig();

        // sortBy() toggles when the same column returns — correct for a header,
        // wrong for a dropdown, where re-picking the current option would
        // silently reverse the listing.
        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->set('sort', 'name')
            ->assertSet('direction', 'DESC')
            ->set('sort', 'name')
            ->assertSet('direction', 'DESC');
    }

    #[Test]
    public function a_sort_column_outside_the_allowlist_falls_back_to_the_key(): void
    {
        $this->makeConfig();

        // The select's value arrives from the client. Ordering by a column the
        // user cannot read turns the listing into an oracle for it.
        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->set('sort', 'secret_salary')
            ->assertSet('sort', 'id');
    }

    #[Test]
    public function a_tampered_direction_is_normalised(): void
    {
        $this->makeConfig();

        // Laravel's orderBy() throws on anything but asc/desc, so an unchecked
        // value turns the whole listing into a 500.
        Livewire::test(BaseCrud::class, ['model' => ResponsiveViewStub::class])
            ->set('direction', 'ASC; DROP TABLE users')
            ->assertSet('direction', 'DESC');
    }
}
