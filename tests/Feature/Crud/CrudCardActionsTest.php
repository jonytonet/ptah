<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class CardActionStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}

/**
 * Configured `action` columns were rendered by the table view and ignored by the
 * card view.
 *
 * That was a gap while cards were something a user opted into. It became a real
 * hole in 1.25.0, when cards became the DEFAULT view on a phone: every custom
 * action an integrator had configured — duplicate, print, open-in-ERP, whatever
 * — was simply unreachable on mobile, with nothing on screen to say so.
 *
 * Both views now include the same partial, so these tests assert the SAME
 * expectations against both view modes. Copying the block instead would have
 * meant copying the `javascript:`/`data:` href guard as well, and a security
 * check is the last thing that should exist in two places.
 */
class CrudCardActionsTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $action
     */
    private function makeConfig(array $action): void
    {
        CrudConfig::updateOrCreate(
            ['model' => CardActionStub::class, 'route' => ''],
            ['config' => [
                'crud' => CardActionStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsVisibleList' => true],
                    array_merge([
                        'colsNomeFisico' => 'acao',
                        'colsNomeLogico' => 'Duplicar',
                        'colsTipo' => 'action',
                        'colsVisibleList' => true,
                    ], $action),
                ],
                'permissions' => [],
            ]]
        );
    }

    private function render(string $viewMode): string
    {
        CardActionStub::create(['name' => 'linha-1']);

        return Livewire::test(BaseCrud::class, ['model' => CardActionStub::class])
            ->call('setViewMode', $viewMode)
            ->html();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function viewModeProvider(): array
    {
        return [
            'table' => ['table'],
            'cards' => ['cards'],
        ];
    }

    #[Test]
    #[DataProvider('viewModeProvider')]
    public function a_livewire_action_renders_in_both_views(string $viewMode): void
    {
        $this->makeConfig([
            'actionType' => 'livewire',
            'actionValue' => 'duplicateRecord(%id%)',
            'actionIcon' => 'bx bx-copy',
        ]);

        $html = $this->render($viewMode);

        $this->assertStringContainsString(
            'duplicateRecord(1)',
            $html,
            "A acao configurada nao aparece na visao '{$viewMode}' — o %id% tambem precisa ser substituido."
        );
        $this->assertStringContainsString('bx bx-copy', $html);
    }

    #[Test]
    #[DataProvider('viewModeProvider')]
    public function a_link_action_renders_in_both_views(string $viewMode): void
    {
        $this->makeConfig([
            'actionType' => 'link',
            'actionValue' => 'https://erp.example/items/%id%',
        ]);

        $html = $this->render($viewMode);

        $this->assertStringContainsString('https://erp.example/items/1', $html);
    }

    #[Test]
    #[DataProvider('viewModeProvider')]
    public function a_dangerous_href_scheme_is_neutralised_in_both_views(string $viewMode): void
    {
        // The reason the block is shared rather than copied. `actionValue` comes
        // from crud_configs, which the visual modal edits, and HTML escaping does
        // NOT neutralise javascript: inside an href. A copy of this block in the
        // card view would have needed its own copy of the guard — and the guard
        // is precisely what a copy forgets.
        $this->makeConfig([
            'actionType' => 'link',
            'actionValue' => 'javascript:alert(document.cookie)',
        ]);

        $html = $this->render($viewMode);

        $this->assertStringNotContainsString(
            'javascript:alert',
            $html,
            "Esquema javascript: sobreviveu no href na visao '{$viewMode}'."
        );
        $this->assertStringContainsString('href="#"', $html);
    }

    #[Test]
    #[DataProvider('viewModeProvider')]
    public function a_data_uri_scheme_is_neutralised_in_both_views(string $viewMode): void
    {
        $this->makeConfig([
            'actionType' => 'link',
            'actionValue' => 'data:text/html;base64,PHNjcmlwdD4=',
        ]);

        $html = $this->render($viewMode);

        $this->assertStringNotContainsString('data:text/html', $html);
    }

    #[Test]
    public function the_card_footer_appears_for_a_configured_action_even_with_no_standard_permission(): void
    {
        // The footer used to be gated on canUpdate || canDelete. A read-only
        // screen whose only affordance is a configured action would have
        // rendered no footer at all, hiding the action a second way.
        $this->makeConfig([
            'actionType' => 'livewire',
            'actionValue' => 'duplicateRecord(%id%)',
            'actionIcon' => 'bx bx-copy',
        ]);

        CardActionStub::create(['name' => 'linha-1']);

        $html = Livewire::test(BaseCrud::class, ['model' => CardActionStub::class])
            ->call('setViewMode', 'cards')
            ->html();

        $this->assertStringContainsString('duplicateRecord(1)', $html);
    }

    #[Test]
    public function the_two_views_render_the_same_action_markup(): void
    {
        // Pins the reason for the shared partial: if the two ever diverge again,
        // this fails even when both happen to render *something*.
        $this->makeConfig([
            'actionType' => 'livewire',
            'actionValue' => 'duplicateRecord(%id%)',
            'actionIcon' => 'bx bx-printer',
            'actionColor' => 'info',
        ]);

        $table = $this->render('table');
        $cards = $this->render('cards');

        foreach ([
            'wire:click="duplicateRecord(1)"',
            'bx bx-printer',
            'text-info',
            'title="Duplicar"',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $table, "Ausente na tabela: {$fragment}");
            $this->assertStringContainsString($fragment, $cards, "Ausente nos cards: {$fragment}");
        }
    }
}
