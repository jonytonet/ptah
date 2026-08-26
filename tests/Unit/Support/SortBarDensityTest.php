<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The card view's sort controls must stay on the density axis.
 *
 * The first version of that CSS pinned `min-height: 2.75rem` below 640px, on
 * the theory that a 36px control is under the 44px touch-target guideline. The
 * effect was to lift exactly two controls off `--ptah-control-h`: they rendered
 * 44px tall while the toolbar directly above them rendered 36px (comfortable)
 * or 28px (compact). A user spotted the mismatch immediately — `min-height`
 * beats `height`, so any floor here silently cancels the density recipe.
 *
 * Someone bigger targets is a real need with a real lever already in the
 * package: the profile's "spacious" density, which raises every control
 * together instead of singling two out. This test keeps the shortcut closed.
 */
class SortBarDensityTest extends TestCase
{
    private const CSS_RELATIVE = 'resources/css/ptah-components.css';

    /**
     * Comments are stripped BEFORE scanning. The rule this test enforces is
     * explained in a comment that necessarily quotes the very declaration it
     * forbids — a guard tripping on its own prose is the single most recurrent
     * false failure in this repository (six occurrences and counting).
     */
    private static function cssWithoutComments(): string
    {
        $path = dirname(__DIR__, 3).'/'.self::CSS_RELATIVE;
        $css = file_get_contents($path);

        if ($css === false) {
            throw new RuntimeException('SortBarDensityTest: falha ao ler '.self::CSS_RELATIVE);
        }

        return (string) preg_replace('#/\*.*?\*/#s', '', $css);
    }

    /**
     * Every declaration block whose selector list mentions a sort-bar class.
     *
     * @return array<int, array{selector: string, body: string}>
     */
    private static function sortBarBlocks(): array
    {
        $css = self::cssWithoutComments();
        $blocks = [];

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $selector = trim($match[1]);

            if (! str_contains($selector, 'ptah-c-sortbar')) {
                continue;
            }

            $blocks[] = ['selector' => $selector, 'body' => $match[2]];
        }

        return $blocks;
    }

    #[Test]
    public function the_sort_bar_css_exists_at_all(): void
    {
        // Guards the guard: a rename that emptied sortBarBlocks() would make
        // every assertion below pass by vacuum.
        $this->assertNotEmpty(
            self::sortBarBlocks(),
            'Nenhum bloco .ptah-c-sortbar* encontrado — o parser quebrou ou as classes foram renomeadas sem atualizar este teste.'
        );
    }

    #[Test]
    public function no_sort_bar_rule_overrides_the_density_driven_height(): void
    {
        $offenders = [];

        foreach (self::sortBarBlocks() as $block) {
            if (preg_match('/(?<![\w-])(min-height|max-height|height)\s*:/i', $block['body'], $m) === 1) {
                $offenders[] = $block['selector'].' → '.trim($m[0]);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Regra de altura em controle da barra de ordenacao: isso o tira do eixo de densidade '.
            "(--ptah-control-h, aplicado por .ptah-c-control) e desalinha da toolbar.\n".
            "Para controles maiores, use a densidade 'espacosa' do perfil.\n".
            implode("\n", $offenders)
        );
    }

    #[Test]
    public function the_sort_bar_controls_opt_into_the_shared_control_class(): void
    {
        $blade = file_get_contents(
            dirname(__DIR__, 3).'/resources/views/livewire/base-crud/partials/_sort-bar.blade.php'
        );

        if ($blade === false) {
            throw new RuntimeException('SortBarDensityTest: falha ao ler _sort-bar.blade.php');
        }

        // Height comes from .ptah-c-control and nowhere else; without the class
        // the controls have no height at all from the design system.
        $this->assertSame(
            2,
            preg_match_all('/ptah-c-control/', $blade),
            'O <select> e o botao de sentido precisam AMBOS de .ptah-c-control — e dela que vem a altura por densidade.'
        );
    }
}
