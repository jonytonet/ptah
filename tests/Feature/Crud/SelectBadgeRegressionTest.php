<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\ColumnParser;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudForm;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudRenderers;
use Ptah\Services\Validation\ConfigSchemaValidator;
use Ptah\Tests\TestCase;

class SelectBadgeHarness
{
    use HasCrudForm, HasCrudRenderers;

    public array $crudConfig = [];

    public string $model = 'Test';
}

/**
 * `colsTipo: select` with `renderer: badge` — the most common column in any
 * CRUD, and it worked through no documented path at all.
 *
 * Two outcomes, neither of them the one intended:
 *
 *   select + badges=            → ConfigValidationException, nothing saved
 *   select + badges= + options= → saves, validates, badge never coloured
 *
 * The second is the dangerous one. `HasCrudRenderers` mapped the value to its
 * `colsSelect` label BEFORE calling the renderer, so `renderBadge()` received
 * "Ativo" and looked for "active"; nothing matched, the loop fell through, and
 * the fallback rendered a GREY badge carrying the right text. No error, no log,
 * and a column that is visibly present — so nobody investigates it.
 *
 * ── Why the existing tests were all green ────────────────────────────────
 *
 * `CrudRenderersTest` covered the badge renderer well: colour, hex colour,
 * case-insensitive matching, escaping, the grey fallback. Every one of those
 * configured a badge whose `value` matched the value it passed in — the
 * renderer in isolation was never broken. The defect lived in the ELEVEN LINES
 * ABOVE the renderer, and only for a `select` column, which no colour
 * assertion combined.
 *
 * There was even a guard for the combination —
 * `badge_on_a_select_column_still_uses_the_mapped_label()` — and it passed,
 * because it asserted the LABEL and the fallback badge carries the label. What
 * it never asserted was the colour, which is the only thing a badge is for.
 * That is the shape of this test: assert the colour class.
 *
 * ── The fix, and why the match widened instead of moving ─────────────────
 *
 * The cure was already written eleven lines up, for `boolean`, with the
 * reasoning spelled out; it had simply not been extended to the two renderers
 * that decide their own label the same way. So `$rendererOwnsLabel` now covers
 * badge and pill.
 *
 * But hosts had adapted to the broken behaviour, keying their badges by the
 * LABEL because that was the only thing that worked, and the guard above wrote
 * that down as the contract. Fixing the flip alone would have turned their
 * coloured badges grey — the same silent failure, in the other direction. So
 * `findBadgeEntry()` tries the raw value first and the `colsSelect` label
 * second. Both configurations colour, and the documented one is now the
 * primary.
 */
class SelectBadgeRegressionTest extends TestCase
{
    private SelectBadgeHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->harness = new SelectBadgeHarness;
    }

    /**
     * @param  array<string, mixed>  $col
     * @param  array<string, mixed>  $row
     */
    private function format(array $col, array $row): string
    {
        return $this->harness->formatCell($col + ['colsNomeFisico' => 'status'], $row);
    }

    /**
     * @return array<string, mixed>
     */
    private static function documentedColumn(string $renderer = 'badge'): array
    {
        return [
            'colsTipo' => 'select',
            'colsSelect' => ['Ativo' => 'active', 'Inativo' => 'inactive'],
            'colsRenderer' => $renderer,
            'colsRendererBadges' => [
                ['value' => 'active', 'color' => 'green', 'label' => 'Ativo'],
                ['value' => 'inactive', 'color' => 'red', 'label' => 'Inativo'],
            ],
        ];
    }

    #[Test]
    public function a_select_column_with_a_badge_renderer_is_actually_coloured(): void
    {
        // The reported case, configured exactly as the documentation teaches:
        // badge `value` holds the COLUMN value, and the label comes along.
        $html = $this->format(self::documentedColumn(), ['status' => 'active']);

        $this->assertStringContainsString(
            'Ativo',
            $html,
            'O rótulo do badge tem de aparecer.'
        );

        // A cor é a asserção que faltava: antes da correção o badge saía cinza
        // com este mesmo rótulo, e é por isso que ninguém percebeu.
        $this->assertStringContainsString(
            'green',
            $html,
            'O badge saiu sem a cor configurada — é o defeito relatado: o mapa '.
            'colsSelect era aplicado ANTES do renderer, então renderBadge() '.
            'recebia "Ativo" e procurava "active".'
        );

        $this->assertStringNotContainsString(
            'gray',
            $html,
            'Badge cinza significa que nenhuma entrada casou — o fallback.'
        );
    }

    #[Test]
    public function the_second_value_of_the_same_column_gets_its_own_colour(): void
    {
        // Um badge que casa por acidente numa única entrada não prova nada.
        $html = $this->format(self::documentedColumn(), ['status' => 'inactive']);

        $this->assertStringContainsString('Inativo', $html);
        $this->assertStringContainsString('red', $html);
    }

    #[Test]
    public function the_pill_renderer_had_the_same_defect_and_the_same_cure(): void
    {
        // `renderPill()` era uma segunda cópia das mesmas vinte linhas, e por
        // isso carregava o mesmo defeito duas vezes. Agora as duas dividem o
        // corpo, e este teste é o que impede a divergência de voltar.
        $html = $this->format(self::documentedColumn('pill'), ['status' => 'active']);

        $this->assertStringContainsString('Ativo', $html);
        $this->assertStringContainsString('green', $html);
        $this->assertStringContainsString('rounded-full', $html, 'Pill mantém a própria forma.');
    }

    #[Test]
    public function a_hex_colour_still_reaches_the_inline_style(): void
    {
        $col = self::documentedColumn();
        $col['colsRendererBadges'][0]['color'] = '#ff0000';

        $html = $this->format($col, ['status' => 'active']);

        $this->assertStringContainsString('background-color:#ff000014', $html);
        $this->assertStringContainsString('color:#ff0000', $html);
    }

    #[Test]
    public function a_badge_keyed_by_the_label_keeps_working(): void
    {
        // O contorno que os hosts escreveram enquanto o defeito existia — e que
        // `badge_on_a_select_column_still_uses_the_mapped_label()` fixou como
        // contrato. Corrigir só o flip apagaria a cor deles sem aviso.
        $html = $this->format([
            'colsTipo' => 'select',
            'colsSelect' => ['Administrador' => 'admin'],
            'colsRenderer' => 'badge',
            'colsRendererBadges' => [['value' => 'Administrador', 'color' => 'green', 'label' => 'Administrador']],
        ], ['status' => 'admin']);

        $this->assertStringContainsString('Administrador', $html);
        $this->assertStringContainsString('green', $html);
    }

    #[Test]
    public function an_unmatched_value_still_shows_the_label_not_the_raw_value(): void
    {
        // A regressão que a correção poderia ter introduzido: o renderer agora
        // recebe o valor CRU, então o fallback tem de buscar o rótulo em
        // colsSelect — senão uma coluna select passaria a exibir "active" onde
        // exibia "Ativo".
        $html = $this->format([
            'colsTipo' => 'select',
            'colsSelect' => ['Ativo' => 'active'],
            'colsRenderer' => 'badge',
            'colsRendererBadges' => [['value' => 'outro', 'color' => 'green', 'label' => 'Outro']],
        ], ['status' => 'active']);

        $this->assertStringContainsString('Ativo', $html);
        $this->assertStringNotContainsString('active<', $html, 'O valor cru não deve chegar à tela.');
        $this->assertStringContainsString('gray', $html, 'Sem badge que case, o fallback é cinza — isso é correto.');
    }

    #[Test]
    public function a_select_column_without_a_renderer_still_gets_the_mapped_label(): void
    {
        // O flip continua valendo onde ele serve: sem renderer, a célula tem de
        // mostrar o rótulo. A correção restringiu o flip, e restringir demais
        // apagaria isto.
        $html = $this->format([
            'colsTipo' => 'select',
            'colsSelect' => ['Ativo' => 'active'],
        ], ['status' => 'active']);

        $this->assertStringContainsString('Ativo', $html);
    }

    #[Test]
    public function the_boolean_case_that_was_already_fixed_is_untouched(): void
    {
        // A exceção original, mantida: renderBoolean() decide o rótulo a partir
        // do valor cru (1/0), e receber "Sim" o levaria a responder sempre
        // "Não".
        $html = $this->format([
            'colsTipo' => 'select',
            'colsSelect' => ['Sim' => '1', 'Não' => '0'],
            'colsRenderer' => 'boolean',
        ], ['status' => '1']);

        $this->assertStringContainsString('bg-green-50', $html);
    }

    // ── O caminho do CLI, que morria antes de chegar ao renderer ────────────

    #[Test]
    public function the_documented_cli_definition_now_produces_a_usable_column(): void
    {
        // O exemplo literal de ptah-development/SKILL.md:431. Sem `options=`,
        // e antes desta correção o ConfigSchemaValidator recusava a coluna
        // inteira com "requires colsSelect to be configured".
        $col = (new ColumnParser)->parse(
            'is_active:select:label=Status:renderer=badge:badges=1|success|Ativo,0|danger|Inativo'
        );

        $this->assertSame(
            ['Ativo' => '1', 'Inativo' => '0'],
            $col['colsSelect'] ?? null,
            'As entradas de badge já são pares valor/rótulo; colsSelect deveria ser derivado delas.'
        );

        unset($col[ColumnParser::EXPLICIT_KEYS]);

        // E a definição derivada tem de passar pelo validador que a recusava.
        (new ConfigSchemaValidator)->validate(
            ['crud' => 'Test', 'cols' => [$col + ['colsNomeLogico' => 'Status']]],
            'Test'
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function the_derived_column_renders_coloured_end_to_end(): void
    {
        // Da string do CLI até o HTML, que é o percurso que o relato descreve.
        $col = (new ColumnParser)->parse(
            'is_active:select:renderer=badge:badges=1|success|Ativo,0|danger|Inativo'
        );

        $html = $this->format($col + ['colsNomeFisico' => 'is_active'], ['is_active' => '1']);

        $this->assertStringContainsString('Ativo', $html);
        $this->assertStringContainsString('green', $html, 'success mapeia para a paleta verde.');
    }

    #[Test]
    public function an_explicit_options_still_wins_over_the_derivation(): void
    {
        // Quem quer as opções do formulário diferentes das cores da listagem
        // escreve as duas, e a derivação não pode atropelar.
        $col = (new ColumnParser)->parse(
            'status:select:options=active:Ligado,inactive:Desligado:renderer=badge:badges=active|green|Ativo'
        );

        $this->assertSame(['Ligado' => 'active', 'Desligado' => 'inactive'], $col['colsSelect'] ?? null);
    }

    #[Test]
    public function the_derivation_only_touches_select_columns(): void
    {
        // Uma coluna de texto com badge não ganha um select que ninguém pediu.
        $col = (new ColumnParser)->parse('status:text:renderer=badge:badges=active|green|Ativo');

        $this->assertArrayNotHasKey('colsSelect', $col);
    }
}
