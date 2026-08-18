<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guard for the toolbar's responsive label collapse (Filtros, Lixeira,
 * Exportar, Colunas, Densidade, Config): the text label of these buttons is
 * shown or hidden by MEASUREMENT (see the inline Alpine component at the top
 * of _toolbar.blade.php), toggling the `.ptah-c-toolbar_labels` class on the
 * row, which the CSS uses to show/hide every `.ptah-c-btn_label` span — not
 * by a viewport breakpoint (a `hidden {bp}:inline` was tried and rejected:
 * it hid the label whenever the viewport was narrow, whether or not the row
 * actually had room).
 *
 * An icon-only button has no accessible name unless the trigger element
 * itself carries a non-empty `title` or `aria-label` — the text that WOULD
 * have supplied it is exactly the thing that can disappear at runtime. This
 * is easy to forget on the next button added to the group (icon-only by
 * default, label added later without the pair), so it is pinned here
 * instead of relying on manual review every time.
 *
 * Pure file reads + regex, no app boot needed — same idiom as
 * ToolbarControlUniformityTest / ContrastGuardTest.
 */
class ToolbarCollapsibleLabelAccessibilityTest extends TestCase
{
    private static function toolbarBlade(): string
    {
        static $blade = null;

        return $blade ??= self::read('resources/views/livewire/base-crud/partials/_toolbar.blade.php');
    }

    private static function crudConfigBlade(): string
    {
        static $blade = null;

        return $blade ??= self::read('resources/views/livewire/base-crud/crud-config.blade.php');
    }

    private static function read(string $relative): string
    {
        $content = file_get_contents(dirname(__DIR__, 3).'/'.$relative);

        if ($content === false) {
            throw new RuntimeException('ToolbarCollapsibleLabelAccessibilityTest: falha ao ler '.$relative);
        }

        return $content;
    }

    /**
     * Every raw <button> in the toolbar whose label is collapsible (carries a
     * `.ptah-c-btn_label` span somewhere inside it, shown/hidden by the
     * measurement, not by a media query) must declare a non-empty
     * `aria-label` or `title` on the button's own opening tag — not on some
     * unrelated nested element (a badge, a dropdown item) that happens to
     * also have a title.
     */
    #[Test]
    public function every_toolbar_button_with_a_collapsible_label_has_an_accessible_name(): void
    {
        $checked = 0;

        foreach (self::collapsibleButtonBlocks(self::toolbarBlade()) as [$openTag, $fullBlock]) {
            $checked++;

            $hasName = self::hasNonEmptyAttribute($openTag, 'aria-label')
                || self::hasNonEmptyAttribute($openTag, 'title');

            $this->assertTrue(
                $hasName,
                "Botao da toolbar com rotulo colapsavel (span '.ptah-c-btn_label') sem aria-label nem title ".
                'no proprio elemento <button>. Quando a medicao decide colapsar, ele fica so com o icone e '.
                "sem nome acessivel para leitor de tela.\nTrecho:\n".substr($fullBlock, 0, 200)
            );
        }

        $this->assertGreaterThan(
            0,
            $checked,
            'Nenhum <button> com rotulo colapsavel foi encontrado em _toolbar.blade.php — '.
            'o regex de deteccao pode ter parado de casar com a marcacao atual.'
        );
    }

    /**
     * Same guarantee for the "Config" trigger, which ships from
     * crud-config.blade.php as <x-forge-button> (a component tag, not a raw
     * <button>) and therefore is not covered by the toolbar scan above.
     */
    #[Test]
    public function the_config_trigger_has_an_accessible_name(): void
    {
        $blade = self::crudConfigBlade();

        $this->assertSame(
            1,
            preg_match('/<x-forge-button\b'.self::OPEN_TAG_ATTRS.'>(.*?)<\/x-forge-button>/s', $blade, $m),
            'ToolbarCollapsibleLabelAccessibilityTest: <x-forge-button> do trigger "Config" nao encontrado em crud-config.blade.php.'
        );

        [, $openTag, $innerContent] = $m;

        $this->assertStringContainsString(
            'ptah-c-btn_label',
            $innerContent,
            'O trigger "Config" nao carrega mais um rotulo colapsavel (.ptah-c-btn_label) — se isso for deliberado, remova este teste; '.
            'se nao for, a marcacao mudou e a deteccao parou de casar.'
        );

        $hasName = self::hasNonEmptyAttribute($openTag, 'aria-label')
            || self::hasNonEmptyAttribute($openTag, 'title');

        $this->assertTrue(
            $hasName,
            'O trigger "Config" (<x-forge-button>) tem rotulo colapsavel mas nenhum :aria-label/:title (ou '.
            'aria-label/title) nao-vazio no proprio elemento — abaixo do breakpoint fica so com o icone e sem '.
            'nome acessivel.'
        );
    }

    /*
     * REMOVIDO: every_collapsible_label_uses_the_same_breakpoint().
     *
     * Exigia que todos os rotulos usassem UM breakpoint. O colapso deixou de ser media
     * query — passou a ser medido — e the_labels_collapse_by_measurement_and_not_by_breakpoint()
     * agora PROIBE qualquer breakpoint. Manter os dois seria exigir e proibir o mesmo.
     */

    /**
     * @return array<int, array{0: string, 1: string}> pairs of [opening tag attributes, full block]
     */
    private static function collapsibleButtonBlocks(string $blade): array
    {
        preg_match_all('/<button\b'.self::OPEN_TAG_ATTRS.'>(.*?)<\/button>/s', $blade, $matches, PREG_SET_ORDER);

        $blocks = [];

        foreach ($matches as $match) {
            [$full, $openTag, $inner] = $match;

            // Detecta pela classe que a medicao controla, nao por breakpoint: o colapso
            // deixou de ser media query (ver o teste que proibe breakpoint abaixo).
            if (str_contains($inner, 'ptah-c-btn_label')) {
                $blocks[] = [$openTag, $full];
            }
        }

        return $blocks;
    }

    /**
     * Matches the attribute run of an opening tag, treating a `>` INSIDE a
     * quoted attribute value as text rather than the tag's real closing `>`.
     * Without this, a Blade condition like
     * `class="... {{ $hiddenColumnsCount > 0 ? 'a' : 'b' }}"` closes the
     * capture group at that literal `>`, silently truncating the attribute
     * list before the aria-label/title that come right after it — which is
     * exactly the false positive this test produced on the "Colunas" button
     * the first time it ran.
     */
    private const OPEN_TAG_ATTRS = '((?:"[^"]*"|\'[^\']*\'|[^"\'>])*)';

    private static function hasNonEmptyAttribute(string $openTag, string $attribute): bool
    {
        $pattern = sprintf('/:?%s="([^"]*)"/', preg_quote($attribute, '/'));

        if (preg_match($pattern, $openTag, $m) !== 1) {
            return false;
        }

        // A dynamic `:title="..."` binding is presumed non-empty at runtime
        // (its value is a PHP expression, e.g. a translation call) unless the
        // expression is a literal empty string.
        return trim($m[1]) !== '' && trim($m[1]) !== "''";
    }

    /**
     * The labels must be decided by MEASUREMENT, never by a viewport breakpoint.
     *
     * A `hidden lg:inline` hides the text whenever the viewport is narrow, regardless of
     * whether it would actually fit — it was shipped once and rejected in testing, with a
     * screenshot showing icon-only buttons and plenty of room to spare. Viewport width
     * cannot answer the real question, which depends on the CONTAINER width (a collapsed
     * sidebar changes it), the chosen density, and the length of the translated strings.
     *
     * This also catches the other half of that regression: the collapse hook referenced an
     * Alpine function that was never defined, so the labels stayed hidden forever and the
     * console threw. Asserting the measurement is INLINE keeps the definition next to its
     * only call site.
     */
    #[Test]
    public function the_labels_collapse_by_measurement_and_not_by_breakpoint(): void
    {
        // Comentario Blade sai antes da varredura: a prosa que explica POR QUE o
        // breakpoint e proibido contem o proprio literal proibido, e uma guarda que
        // grepa o arquivo inteiro reprova a si mesma. Terceira vez que essa armadilha
        // aparece neste repo (ver CssDeclarationExtractor::styleBlockFromBlade).
        $blade = preg_replace('/\{\{--.*?--\}\}/s', '', self::toolbarBlade()) ?? self::toolbarBlade();

        foreach (['hidden sm:inline', 'hidden md:inline', 'hidden lg:inline', 'hidden xl:inline'] as $bp) {
            $this->assertStringNotContainsString(
                $bp,
                $blade,
                sprintf(
                    'A toolbar voltou a esconder rotulo por breakpoint ("%s"). O rotulo deve '.
                    'aparecer sempre que couber e colapsar so quando a linha quebraria, o que '.
                    'depende da largura do container, da densidade e do idioma — nenhum dos '.
                    'tres e visivel para uma media query. Use .ptah-c-btn_label mais a medicao.',
                    $bp
                )
            );
        }

        $this->assertStringContainsString(
            'ptah-c-btn_label',
            $blade,
            'Os rotulos colapsaveis perderam a classe .ptah-c-btn_label, que e o que a medicao controla.'
        );

        $this->assertStringContainsString(
            'ptah-c-toolbar_actions',
            $blade,
            'O grupo de acoes perdeu .ptah-c-toolbar_actions. A medicao precisa dele: esse grupo e '.
            'um flex proprio e pode quebrar internamente sem a linha externa quebrar.'
        );

        $this->assertStringContainsString(
            '_measure()',
            $blade,
            'A medicao inline desapareceu do container da toolbar.'
        );

        $this->assertStringNotContainsString(
            'ptahToolbarActions',
            $blade,
            'A toolbar voltou a chamar uma funcao Alpine externa. Ela ja foi referenciada uma vez '.
            'sem nunca ser definida — os rotulos ficaram permanentemente escondidos e o console '.
            'quebrou, sem nenhum teste perceber. Mantenha a logica inline, junto do unico uso.'
        );
    }
}
