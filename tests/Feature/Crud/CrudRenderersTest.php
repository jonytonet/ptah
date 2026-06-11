<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudForm;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudRenderers;
use Ptah\Tests\TestCase;

// ── Harness exposing the public renderer API ──────────────────────────────────

class RendererHarness
{
    use HasCrudForm, HasCrudRenderers;

    public array $crudConfig = [];

    public string $model = 'Test';
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Covers HasCrudRenderers — the cell formatting DSL. The crucial property is
 * XSS safety: every user-controlled value must be escaped unless the column
 * explicitly opts into raw HTML.
 */
class CrudRenderersTest extends TestCase
{
    private RendererHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->harness = new RendererHarness;
    }

    private function format(array $col, array $row): string
    {
        return $this->harness->formatCell($col + ['colsNomeFisico' => 'field'], $row);
    }

    // ── XSS / escaping ────────────────────────────────────────────────────────

    #[Test]
    public function plain_values_are_html_escaped(): void
    {
        $html = $this->format([], ['field' => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function badge_fallback_escapes_unmatched_values(): void
    {
        $html = $this->format(
            ['colsRenderer' => 'badge', 'colsRendererBadges' => [['value' => 'known', 'label' => 'Known', 'color' => 'green']]],
            ['field' => '<img src=x onerror=alert(1)>'],
        );

        $this->assertStringNotContainsString('<img', $html);
    }

    #[Test]
    public function badge_label_from_config_is_escaped(): void
    {
        $html = $this->format(
            ['colsRenderer' => 'badge', 'colsRendererBadges' => [['value' => 'x', 'label' => '<b>bold</b>', 'color' => 'green']]],
            ['field' => 'x'],
        );

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }

    // ── Badge / pill / boolean ────────────────────────────────────────────────

    #[Test]
    public function badge_matches_value_case_insensitively_and_applies_the_color(): void
    {
        $html = $this->format(
            ['colsRenderer' => 'badge', 'colsRendererBadges' => [['value' => 'Active', 'label' => 'Ativo', 'color' => 'green']]],
            ['field' => 'ACTIVE'],
        );

        $this->assertStringContainsString('Ativo', $html);
        $this->assertStringContainsString('bg-green-100', $html);
    }

    #[Test]
    public function badge_supports_hex_colors(): void
    {
        $html = $this->format(
            ['colsRenderer' => 'badge', 'colsRendererBadges' => [['value' => 'x', 'label' => 'X', 'color' => '#ff0000']]],
            ['field' => 'x'],
        );

        $this->assertStringContainsString('#ff0000', $html);
        $this->assertStringContainsString('style=', $html);
    }

    #[Test]
    public function boolean_renderer_recognises_truthy_variants(): void
    {
        foreach ([1, '1', 'S', 'true', true, 'Y'] as $truthy) {
            $html = $this->format(['colsRenderer' => 'boolean'], ['field' => $truthy]);
            $this->assertStringContainsString('bg-green-100', $html, var_export($truthy, true).' must render as YES');
        }

        $html = $this->format(['colsRenderer' => 'boolean'], ['field' => 0]);
        $this->assertStringContainsString('bg-gray-100', $html);
    }

    #[Test]
    public function boolean_renderer_accepts_custom_labels(): void
    {
        $html = $this->format(
            ['colsRenderer' => 'boolean', 'colsRendererBoolTrue' => 'Enabled'],
            ['field' => 1],
        );

        $this->assertStringContainsString('Enabled', $html);
    }

    // ── Money / date ──────────────────────────────────────────────────────────

    #[Test]
    public function money_renderer_formats_per_currency(): void
    {
        $brl = $this->format(['colsRenderer' => 'money'], ['field' => 1234.5]);
        $this->assertStringContainsString('R$ 1.234,50', $brl);

        $usd = $this->format(['colsRenderer' => 'money', 'colsRendererCurrency' => 'USD'], ['field' => 1234.5]);
        $this->assertStringContainsString('$ 1,234.50', $usd);

        $empty = $this->format(['colsRenderer' => 'money'], ['field' => null]);
        $this->assertSame('', $empty);
    }

    #[Test]
    public function date_renderer_formats_to_brazilian_format(): void
    {
        $html = $this->format(['colsRenderer' => 'date'], ['field' => '2026-06-11']);

        $this->assertSame('11/06/2026', $html);
    }

    #[Test]
    public function date_renderer_keeps_unparseable_values(): void
    {
        $html = $this->format(['colsRenderer' => 'date'], ['field' => 'not-a-date']);

        $this->assertSame('not-a-date', $html);
    }

    // ── Select map / wrappers ─────────────────────────────────────────────────

    #[Test]
    public function select_columns_map_stored_values_back_to_labels(): void
    {
        $html = $this->format(
            ['colsTipo' => 'select', 'colsSelect' => ['Yes' => '1', 'No' => '0']],
            ['field' => '1'],
        );

        $this->assertStringContainsString('Yes', $html);
    }

    #[Test]
    public function cell_class_and_icon_wrappers_are_applied(): void
    {
        $html = $this->format(
            ['colsCellClass' => 'font-bold', 'colsCellIcon' => 'bx bx-star'],
            ['field' => 'value'],
        );

        $this->assertStringContainsString('font-bold', $html);
        $this->assertStringContainsString('bx bx-star', $html);
        $this->assertStringContainsString('value', $html);
    }

    // ── Row styles ────────────────────────────────────────────────────────────

    #[Test]
    public function row_style_applies_when_the_condition_matches(): void
    {
        $this->harness->crudConfig = [
            'contitionStyles' => [
                ['field' => 'status', 'condition' => '==', 'value' => 'urgent', 'style' => 'background: red'],
                ['field' => 'amount', 'condition' => '>', 'value' => 100, 'style' => 'background: gold'],
            ],
        ];

        $this->assertSame('background: red', $this->harness->getRowStyle(['status' => 'urgent', 'amount' => 1]));
        $this->assertSame('background: gold', $this->harness->getRowStyle(['status' => 'ok', 'amount' => 500]));
        $this->assertSame('', $this->harness->getRowStyle(['status' => 'ok', 'amount' => 1]));
    }

    #[Test]
    public function unknown_renderer_falls_back_to_escaped_text(): void
    {
        $html = $this->format(['colsRenderer' => 'nope'], ['field' => '<x>']);

        $this->assertSame('&lt;x&gt;', $html);
    }
}
