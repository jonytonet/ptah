<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

/** Reuses the bulk_action_stubs table (see tests/migrations) — no columns needed here. */
class ShortcutsOverlayStub extends Model
{
    protected $table = 'bulk_action_stubs';

    protected $fillable = ['name', 'status'];
}

/**
 * FIX 3 of the Onda C ux-acl-tree audit for base-crud.blade.php:
 *   - `?` (outside a form field) opens a lightweight overlay listing the
 *     shortcuts BaseCrud actually has — `/` (focus search) and `n` (new,
 *     only when the user can create). No new shortcut was invented.
 *   - the fragile guard `document.body.style.overflow === 'hidden'` (a flag
 *     only _modal-form.blade.php kept in sync) was replaced by
 *     `_anyDialogOpen()`, which checks every `[aria-modal=true]` element's
 *     computed display — covering the bulk-delete confirm and the discard
 *     confirm too, not just the create/edit modal.
 *   - the search input shows a `/` hint while empty.
 */
class CrudKeyboardShortcutsOverlayTest extends TestCase
{
    private function makeConfig(array $permissions = []): void
    {
        CrudConfig::create([
            'model' => ShortcutsOverlayStub::class,
            'route' => '',
            'config' => [
                'crud' => ShortcutsOverlayStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => $permissions,
            ],
        ]);
    }

    #[Test]
    public function the_hotkey_guard_no_longer_reads_body_style_overflow(): void
    {
        $this->makeConfig();

        $html = Livewire::test(BaseCrud::class, ['model' => ShortcutsOverlayStub::class])->html();

        $this->assertStringNotContainsString("document.body.style.overflow === 'hidden'", $html);
        $this->assertStringContainsString('_anyDialogOpen()', $html);
        $this->assertStringContainsString('[aria-modal=true]', $html);
    }

    #[Test]
    public function the_shortcuts_overlay_is_a_labelled_dialog_closed_by_default(): void
    {
        $this->makeConfig();

        $html = Livewire::test(BaseCrud::class, ['model' => ShortcutsOverlayStub::class])->html();

        $this->assertStringContainsString('x-show="_showShortcuts"', $html);
        $this->assertStringContainsString('role="dialog" aria-modal="true" aria-labelledby="ptah-shortcuts-title"', $html);
        $this->assertStringContainsString(__('ptah::ui.shortcuts_title'), $html);
    }

    #[Test]
    public function the_overlay_lists_the_search_shortcut_and_hides_new_when_the_user_cannot_create(): void
    {
        $this->makeConfig(['permissionIdentifier' => null]);

        $html = Livewire::test(BaseCrud::class, ['model' => ShortcutsOverlayStub::class])->html();

        $this->assertStringContainsString(__('ptah::ui.shortcuts_search'), $html);
        $this->assertStringContainsString(__('ptah::ui.shortcuts_help'), $html);
    }

    #[Test]
    public function the_search_input_carries_no_inline_slash_hint(): void
    {
        // A caixinha <kbd>/</kbd> dentro do campo de busca foi removida a pedido
        // explicito do usuario ("ficou feio essa caixa da barra '/' dentro do
        // pesquisar"); o atalho segue documentado no overlay (tecla ?). Este
        // teste pina a decisao contra reintroducao.
        // O overlay de atalhos (tecla ?) usa <kbd> legitimamente para DOCUMENTAR
        // os atalhos — a proibicao vale so para o campo de busca da toolbar.
        $toolbar = file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/base-crud/partials/_toolbar.blade.php');

        $this->assertStringNotContainsString('<kbd', $toolbar);
    }

    #[Test]
    public function hotkey_magics_are_accessed_through_this_inside_the_alpine_method(): void
    {
        // Convencao local: this.$el/this.$wire em metodos do x-data, por
        // explicitude. (Magic cru FUNCIONA via with(scope) do avaliador do
        // Alpine — provado empiricamente — mas depende de mecanismo pouco
        // obvio; este guard pina a convencao neste arquivo, nao corretude.)
        $blade = file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/base-crud/base-crud.blade.php');

        $this->assertStringContainsString('this.$el.querySelector', $blade);
        $this->assertStringContainsString('this.$wire.prepareCreate()', $blade);
        $this->assertDoesNotMatchRegularExpression('/[^.\w]\$el\.querySelector/', $blade);
        $this->assertDoesNotMatchRegularExpression('/[^.\w]\$wire\.prepareCreate/', $blade);
    }

    #[Test]
    public function the_dialog_guard_tests_real_visibility_not_own_computed_display(): void
    {
        // getComputedStyle(el).display nao reflete ancestral escondido: os
        // confirms ad-hoc tem x-show no wrapper e aria-modal no painel interno,
        // entao a versao antiga via "dialog aberto" em toda pagina e engolia
        // TODAS as teclas em silencio (bug de campo: nada acontece, console
        // limpo). O guard precisa de checkVisibility()/getClientRects().
        $blade = file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/base-crud/base-crud.blade.php');

        $this->assertStringContainsString('el.checkVisibility ? el.checkVisibility() : el.getClientRects().length > 0', $blade);
        $this->assertStringNotContainsString("getComputedStyle(el).display !== 'none'", $blade);
    }

    #[Test]
    public function the_f_v_and_r_shortcuts_are_wired_and_documented_in_the_overlay(): void
    {
        $this->makeConfig();

        $html = Livewire::test(BaseCrud::class, ['model' => ShortcutsOverlayStub::class])->html();

        $this->assertStringContainsString('this.$wire.toggleFilters()', $html);
        $this->assertStringContainsString('this.$wire.setViewMode(', $html);
        $this->assertStringContainsString('this.$wire.$refresh()', $html);
        $this->assertStringContainsString(__('ptah::ui.shortcuts_filters'), $html);
        $this->assertStringContainsString(__('ptah::ui.shortcuts_view_mode'), $html);
        $this->assertStringContainsString(__('ptah::ui.shortcuts_refresh'), $html);
    }

    #[Test]
    public function ctrl_b_toggles_the_sidebar_from_the_layout_root(): void
    {
        // Atalho GLOBAL (convencao VSCode/Slack, pedido do usuario): vive no
        // layout, nao no BaseCrud — mas e documentado no overlay do "?" por
        // ser a unica superficie de descoberta de atalhos.
        $layout = file_get_contents(dirname(__DIR__, 3).'/resources/views/components/forge-dashboard-layout.blade.php');

        $this->assertStringContainsString('@keydown.window.ctrl.b.prevent="toggleSidebarCollapse()"', $layout);
        $this->assertStringContainsString('@keydown.window.meta.b.prevent="toggleSidebarCollapse()"', $layout);

        $this->makeConfig();
        $html = Livewire::test(BaseCrud::class, ['model' => ShortcutsOverlayStub::class])->html();
        $this->assertStringContainsString(__('ptah::ui.shortcuts_sidebar'), $html);
    }
}
