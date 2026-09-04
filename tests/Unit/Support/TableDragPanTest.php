<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Drag-to-scroll on the table.
 *
 * A wide listing is genuinely hard to scroll sideways with a mouse — most have
 * no horizontal wheel, and shift+wheel is folklore. Grabbing the table fixes
 * that. The risk is not the scrolling: it is that the table wrapper is already
 * contested territory, and a careless pan handler steals gestures that work
 * today.
 *
 * Three of them, all in _table.blade.php:
 *   `<th draggable="true">`     column reorder, via HTML5 drag
 *   the resize handle           `onmousedown="ptahResizeStart(…)"`
 *   `<tr @click="ptahRowNav">`  row navigation, when configLinkLinha is set
 *
 * plus the sortable header buttons, the selection checkboxes and the row action
 * buttons. So these tests are about what the pan must NOT do. They read the
 * shipped source rather than a browser because each guarantee is a specific
 * line — the pointer-type filter, the ignore list, the threshold, the swallowed
 * click — and a line either exists or it does not.
 */
class TableDragPanTest extends TestCase
{
    private const SCRIPTS = __DIR__.'/../../../resources/views/livewire/base-crud/partials/_scripts.blade.php';

    private const CSS = __DIR__.'/../../../resources/css/ptah-components.css';

    private static function scripts(): string
    {
        $s = file_get_contents(self::SCRIPTS);

        if ($s === false) {
            throw new RuntimeException('TableDragPanTest: falha ao ler _scripts.blade.php');
        }

        // Comments out first: this block explains every guarantee below in
        // prose, and a scanner that reads its own documentation proves nothing.
        // The suite has made that mistake more than once.
        $s = (string) preg_replace('#/\*.*?\*/#s', '', $s);

        return (string) preg_replace('#\{\{--.*?--\}\}#s', '', $s);
    }

    private static function css(): string
    {
        $c = file_get_contents(self::CSS);

        if ($c === false) {
            throw new RuntimeException('TableDragPanTest: falha ao ler ptah-components.css');
        }

        return (string) preg_replace('#/\*.*?\*/#s', '', $c);
    }

    #[Test]
    public function the_pan_only_arms_for_a_mouse(): void
    {
        // Touch and pen scroll natively, better than this code would. Hijacking
        // the gesture there would break the page's own scrolling on a phone —
        // the device where the listing is hardest to use in the first place.
        $this->assertStringContainsString(
            "e.pointerType !== 'mouse'",
            self::scripts(),
            'O pan precisa filtrar por pointerType: em toque a rolagem nativa e melhor.'
        );
    }

    #[Test]
    public function the_pan_only_arms_for_the_primary_button(): void
    {
        // Middle-click is open-in-new-tab (ptahRowNav) and right-click is the
        // context menu. Neither should start a drag.
        $this->assertStringContainsString('e.button !== 0', self::scripts());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function protectedTargetProvider(): array
    {
        return [
            // Owns HTML5 drag for column reorder.
            'th' => ['th'],
            // Sortable header, row actions, bulk actions.
            'button' => ['button'],
            // Row navigation renders links in some configs.
            'a' => ['a'],
            // Selection checkboxes, inline filters.
            'input' => ['input'],
            'select' => ['select'],
            'textarea' => ['textarea'],
            'label' => ['label'],
            // The resize handle and the reorder th both carry it.
            'draggable' => ['[draggable="true"]'],
            // An escape hatch for a host's own custom cell content.
            'opt-out' => ['[data-ptah-no-pan]'],
        ];
    }

    #[Test]
    #[DataProvider('protectedTargetProvider')]
    public function the_pan_never_starts_on_an_element_that_already_reacts(string $selector): void
    {
        $this->assertStringContainsString(
            $selector,
            self::scripts(),
            "'{$selector}' precisa estar na lista de alvos ignorados: comecar um pan nele roubaria o gesto que ja existe ali."
        );
    }

    #[Test]
    public function a_short_gesture_is_still_a_text_selection(): void
    {
        // The delicate one. Suppressing selection on pointerdown would have
        // removed the ability to copy a value out of a cell, silently. The
        // threshold means anything under 5px stays a selection and only a real
        // drag becomes a pan.
        $scripts = self::scripts();

        $this->assertStringContainsString('PAN_THRESHOLD = 5', $scripts);
        $this->assertStringContainsString('Math.abs(dx) < PAN_THRESHOLD', $scripts);
    }

    #[Test]
    public function a_completed_drag_swallows_the_click_it_would_otherwise_fire(): void
    {
        // The browser fires `click` on mouseup even after a 400px drag. Without
        // this, dragging a table that has configLinkLinha would navigate to
        // whichever record happened to be under the cursor at the end.
        $scripts = self::scripts();

        $this->assertStringContainsString('swallow', $scripts);
        $this->assertMatchesRegularExpression(
            '/addEventListener\(\s*.click.,\s*function swallow/',
            $scripts,
            'O clique pos-arraste precisa ser interceptado.'
        );
        // Capture phase, or the row's own @click runs first and navigates.
        $this->assertMatchesRegularExpression(
            '/removeEventListener\(\s*.click., swallow,\s*true\s*\)/',
            $scripts,
            'A interceptacao precisa ser na fase de CAPTURA (o terceiro argumento true).'
        );
    }

    #[Test]
    public function the_grab_cursor_only_appears_when_the_table_can_actually_scroll(): void
    {
        // Offering the affordance on a table that fits would promise a gesture
        // that does nothing.
        $scripts = self::scripts();

        $this->assertStringContainsString('scrollWidth - wrap.clientWidth > 1', $scripts);
        $this->assertStringContainsString("classList.toggle('ptah-is-pannable'", $scripts);

        $this->assertMatchesRegularExpression(
            '/\.ptah-c-tbl_wrap\.ptah-is-pannable\s*\{[^}]*cursor:\s*grab/',
            self::css()
        );
        $this->assertMatchesRegularExpression(
            '/\.ptah-c-tbl_wrap\.ptah-is-panning\s*\{[^}]*cursor:\s*grabbing/',
            self::css()
        );
    }

    #[Test]
    public function interactive_descendants_keep_their_own_cursor(): void
    {
        // `cursor: grab` on the wrapper inherits into every button and
        // checkbox, suggesting a drag where the gesture is a click — and the pan
        // does not even start on those, so cursor and behaviour would disagree.
        // Listas de seletores, nao `:is()`: o parser das fixtures golden deste
        // projeto divide por virgula e nao conhece `:is()`, e a forma compacta
        // gravava chaves sem sentido no baseline (`a|light|cursor`) — uma delas
        // podendo colidir com um seletor real e mascarar uma mudanca de cor.
        $css = self::css();

        $this->assertMatchesRegularExpression(
            '/\.ptah-c-tbl_wrap\.ptah-is-pannable button,/',
            $css,
            'Os descendentes interativos precisam de cursor proprio, em lista de seletores.'
        );
        $this->assertMatchesRegularExpression(
            '/\.ptah-c-tbl_wrap\.ptah-is-pannable \[draggable="true"\]\s*\{[^}]*cursor:\s*pointer/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.ptah-c-tbl_wrap\.ptah-is-pannable select\s*\{[^}]*cursor:\s*auto/',
            $css
        );
    }

    #[Test]
    public function the_behaviour_is_reinstalled_after_a_livewire_render(): void
    {
        // Livewire replaces the table's DOM on every paginate, filter and sort.
        // Binding once at load would leave the pan working until the first
        // interaction and then quietly not.
        $scripts = self::scripts();

        $this->assertStringContainsString("hook('morphed'", $scripts);
        $this->assertStringContainsString('__ptahPan', $scripts, 'Precisa de guarda para nao religar no mesmo elemento.');
    }

    #[Test]
    public function the_pointer_is_captured_so_the_sticky_column_does_not_break_the_drag(): void
    {
        // The actions column is `sticky right-0`, so it sits over the content
        // during a horizontal scroll. Without pointer capture the pan stopped
        // the moment the cursor crossed it.
        $scripts = self::scripts();

        $this->assertStringContainsString('setPointerCapture', $scripts);
        $this->assertStringContainsString('releasePointerCapture', $scripts);
    }
}
