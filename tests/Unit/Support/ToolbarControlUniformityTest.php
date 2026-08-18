<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Regression guard for the toolbar "three heights" bug: every control in
 * _toolbar.blade.php (plus the Config trigger, which ships from a separate
 * Livewire component) must opt into the shared .ptah-c-control height recipe
 * in resources/css/ptah-components.css, and the three density presets that
 * recipe reads from must actually differ from one another (the "Espaçoso"
 * bug was two tiers sharing the exact same padding value).
 *
 * Pure file reads + regex, no app boot needed — same idiom as ContrastGuardTest.
 */
class ToolbarControlUniformityTest extends TestCase
{
    private static function toolbarBlade(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/base-crud/partials/_toolbar.blade.php');
    }

    private static function crudConfigBlade(): string
    {
        static $blade = null;

        return $blade ??= file_get_contents(dirname(__DIR__, 3).'/resources/views/livewire/base-crud/crud-config.blade.php');
    }

    private static function css(): string
    {
        static $css = null;

        return $css ??= file_get_contents(dirname(__DIR__, 3).'/resources/css/ptah-components.css');
    }

    /**
     * One targeted assertion per known toolbar control, tying its own markup
     * to the presence of .ptah-c-control — a plain substring count would pass
     * even if a NEW control forgot the class as long as some other control's
     * count stayed the same overall.
     */
    public static function toolbarControlProvider(): array
    {
        return [
            '+ Novo (x-forge-button)' => ['/<x-forge-button @click="\$wire\.showModal = true; \$wire\.prepareCreate\(\)"[^>]*class="ptah-c-control"/'],
            'Busca (x-forge-input)' => ['/iconBefore=\'[^\']*\'\s*\n\s*class="ptah-c-search ptah-c-control"/'],
            'Filtros' => ['/wire:click="toggleFilters"\s*\n\s*class="[^"]*ptah-c-control/'],
            'Lixeira' => ['/wire:click="toggleTrashed"\s*\n\s*class="[^"]*ptah-c-control/'],
            'Exportar (trigger)' => ['/@click="open = !open"\s*\n\s*class="[^"]*ptah-c-btn ptah-c-control"\s*\n\s*title="\{\{ __\(\'ptah::ui\.btn_export\'\) \}\}"/'],
            'Colunas (trigger)' => ['/@click="open = !open"\s*\n\s*class="[^"]*ptah-c-control\s*\n[^"]*\{\{ \$hiddenColumnsCount/'],
            'Modo tabela' => ['/wire:click="setViewMode\(\'table\'\)"\s*\n\s*class="[^"]*ptah-c-control/'],
            'Modo cards' => ['/wire:click="setViewMode\(\'cards\'\)"\s*\n\s*class="[^"]*ptah-c-control/'],
            'Densidade (trigger)' => ['/@click="open = !open"\s*\n\s*class="[^"]*ptah-c-btn ptah-c-control"\s*\n\s*title="\{\{ __\(\'ptah::ui\.btn_density\'\) \}\}"/'],
            'Atualizar' => ['/wire:click="\$refresh"\s*\n\s*class="[^"]*ptah-c-control/'],
            'Limpar filtros' => ['/wire:click="clearFilters"\s*\n\s*class="[^"]*ptah-c-control/'],
            'Itens por página (select)' => ['/<select wire:model\.live="perPage"[\s\S]{0,220}?ptah-c-perpage ptah-c-control/'],
        ];
    }

    #[Test]
    #[DataProvider('toolbarControlProvider')]
    public function every_toolbar_control_carries_the_shared_height_class(string $pattern): void
    {
        $this->assertMatchesRegularExpression(
            $pattern,
            self::toolbarBlade(),
            'ToolbarControlUniformityTest: um controle da toolbar perdeu (ou nunca ganhou) a classe '.
            '.ptah-c-control — ele voltara a desalinhar dos demais (Problema A: tres alturas diferentes).'
        );
    }

    #[Test]
    public function the_config_trigger_carries_the_shared_height_class(): void
    {
        // The Config button ships from its own Livewire component (ptah-crud-config),
        // not from _toolbar.blade.php, but it renders inline in the same toolbar row.
        if (! preg_match('/wire:click="openModal"[\s\S]{0,120}?class="ptah-c-control"/', self::crudConfigBlade())) {
            throw new RuntimeException(
                'ToolbarControlUniformityTest: o botao "Config" (crud-config.blade.php) perdeu a classe .ptah-c-control.'
            );
        }

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function the_three_densities_produce_different_control_height_and_row_padding(): void
    {
        $css = self::css();

        $heights = [];
        $rowPaddings = [];

        foreach ([
            'comfortable' => '/\.ptah-base-crud\s*\{[^}]*--ptah-control-h:\s*([^;]+);[^}]*--ptah-row-py:\s*([^;]+);/',
            'compact' => '/\.ptah-base-crud\[data-density="compact"\]\s*\{[^}]*--ptah-control-h:\s*([^;]+);[^}]*--ptah-row-py:\s*([^;]+);/',
            'spacious' => '/\.ptah-base-crud\[data-density="spacious"\]\s*\{[^}]*--ptah-control-h:\s*([^;]+);[^}]*--ptah-row-py:\s*([^;]+);/',
        ] as $density => $pattern) {
            if (! preg_match($pattern, $css, $m)) {
                throw new RuntimeException("ToolbarControlUniformityTest: nao encontrei --ptah-control-h/--ptah-row-py para a densidade \"{$density}\".");
            }

            $heights[$density] = trim($m[1]);
            $rowPaddings[$density] = trim($m[2]);
        }

        $this->assertSame(
            3,
            count(array_unique($heights)),
            'ToolbarControlUniformityTest: --ptah-control-h nao difere entre as 3 densidades: '.json_encode($heights)
        );
        $this->assertSame(
            3,
            count(array_unique($rowPaddings)),
            'ToolbarControlUniformityTest: --ptah-row-py nao difere entre as 3 densidades (o bug original: '.
            '"Espacoso" tinha o mesmo padding de linha que "Confortavel") — valores: '.json_encode($rowPaddings)
        );
    }

    /**
     * .ptah-c-control must not set horizontal padding on an <input>.
     *
     * forge-input computes pl-9 to reserve room for an absolutely-positioned
     * iconBefore. This class is unlayered, so a padding-inline reaching the input
     * beats that utility, the reserved space collapses, and the icon ends up drawn
     * on top of the placeholder — which is exactly what shipped on the quick-search
     * box. Height was the axis that needed unifying; horizontal padding on a shared
     * class that also lands on icon inputs is a trap, so the rule is pinned here.
     */
    #[Test]
    public function the_control_class_never_sets_horizontal_padding_on_an_input(): void
    {
        $css = self::css();

        if (! preg_match('/\.ptah-c-control\s*\{([^}]*)\}/', $css, $m)) {
            throw new RuntimeException('ToolbarControlUniformityTest: bloco .ptah-c-control nao encontrado.');
        }

        foreach (['padding-inline', 'padding-left', 'padding-right', 'padding:'] as $prop) {
            $this->assertStringNotContainsString(
                $prop,
                $m[1],
                sprintf(
                    'A regra .ptah-c-control declara "%s", que atinge o <input> da busca rapida. '.
                    'Sendo CSS sem camada, ela vence o pl-9 que o forge-input calcula para o '.
                    'iconBefore, o espaco reservado colapsa e a lupa fica DESENHADA SOBRE o '.
                    'placeholder. Se precisar de padding horizontal, use '.
                    '.ptah-c-control:not(input).',
                    $prop
                )
            );
        }
    }
}
