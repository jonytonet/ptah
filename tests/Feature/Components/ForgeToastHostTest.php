<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * Guards the forge-toast-host.blade.php half of FIX 2 (Onda C ux-acl-tree
 * audit, item 25):
 *   - the auto-dismiss timer pauses on hover/focus (WCAG 2.2.1 Timing
 *     Adjustable) instead of closing a toast a user is still reading/using
 *     (e.g. the Undo action);
 *   - the dismiss (x) button's aria-label was `btn_cancel` ("Cancel"), which
 *     described the wrong action — it now uses `modal_close` ("Close").
 */
class ForgeToastHostTest extends TestCase
{
    private function render(): string
    {
        return (string) $this->blade('<x-forge-toast-host />');
    }

    #[Test]
    public function auto_dismiss_pauses_on_hover_and_resumes_on_mouse_leave(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('@mouseenter="_pause(t)"', $html);
        $this->assertStringContainsString('@mouseleave="_resume(t)"', $html);
    }

    #[Test]
    public function auto_dismiss_pauses_on_focus_and_resumes_on_blur(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('@focusin="_pause(t)"', $html);
        $this->assertStringContainsString('@focusout="_resume(t)"', $html);
    }

    #[Test]
    public function pause_clears_the_timer_and_resume_reschedules_only_the_remaining_time(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('/_pause\(t\)\s*\{\s*clearTimeout\(t\.timer\);/', $html);
        $this->assertMatchesRegularExpression('/_resume\(t\)\s*\{[^}]*setTimeout\(\(\) => this\._dismiss\(t\.id\), t\.duration\);/', $html);
    }

    #[Test]
    public function the_dismiss_button_uses_the_modal_close_label_not_btn_cancel(): void
    {
        $html = $this->render();

        $this->assertStringContainsString(sprintf('aria-label="%s"', e(__('ptah::ui.modal_close'))), $html);
        $this->assertStringNotContainsString(sprintf('aria-label="%s"', e(__('ptah::ui.btn_cancel'))), $html);
    }
}
