<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * The `forge-demo` view (mounted at /ptah-forge-demo in local/testing envs)
 * showcases every Forge component with example props. It is the fastest way
 * to catch a prop/shape mismatch between a demo section and the component it
 * demonstrates — which is exactly how BUG 4 slipped through: the tabs,
 * list and table sections used shapes the real components don't accept.
 *
 * This test would have caught all three: it just renders the whole view and
 * asserts it doesn't throw, plus spot-checks markers for the three fixed
 * sections.
 */
class ForgeDemoRendersTest extends TestCase
{
    #[Test]
    public function forge_demo_view_renders_without_throwing(): void
    {
        $html = view('ptah::forge-demo')->render();

        $this->assertNotEmpty($html);
    }

    #[Test]
    public function tabs_section_renders_all_three_panels_with_content(): void
    {
        $html = view('ptah::forge-demo')->render();

        $this->assertStringContainsString('id="tab-info"', $html);
        $this->assertStringContainsString('Conteúdo da aba <strong>Informações</strong>', $html);
        $this->assertStringContainsString('Conteúdo da aba <strong>Histórico</strong>', $html);
        $this->assertStringContainsString('Conteúdo da aba <strong>Logs</strong>', $html);
    }

    #[Test]
    public function list_section_renders_badges_with_labels(): void
    {
        $html = view('ptah::forge-demo')->render();

        $this->assertStringContainsString('Ativo', $html);
        $this->assertStringContainsString('Inativo', $html);
        $this->assertStringContainsString('Pendente', $html);
    }

    #[Test]
    public function table_section_renders_headers_and_rows(): void
    {
        $html = view('ptah::forge-demo')->render();

        $this->assertStringContainsString('Pedido #001', $html);
        $this->assertStringContainsString('Nome', $html);
    }
}
