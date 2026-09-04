<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\ColumnParser;
use Ptah\Models\CrudConfig;
use Ptah\Support\ModelKey;
use Ptah\Support\SelectOptions;
use Ptah\Tests\TestCase;

class SelectOptionsStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}

/**
 * `ptah:config --column="…:select:options=…"` could not produce a working select,
 * and `--filter=…:options=` could not either. Two independent defects, both
 * silent, both reported "✓ Configuration saved successfully!".
 *
 * 1. The options were stored as the RAW STRING. `colsSelect` has to be a
 *    label => value map, because both the modal form and the filter panel build
 *    their <option> list from `array_keys()`/`array_values()` of it — and
 *    `collect("a|b,c|d")` on a scalar yields `[0 => "a|b,c|d"]`, so the screen
 *    rendered exactly one option, labelled `0`, whose value was the whole
 *    unparsed definition.
 *
 * 2. `--column=` APPENDED. Re-running it for a field that already had a column
 *    produced two entries for the same field, and which one won depended on
 *    iteration order.
 *
 * The filter parser had a third variant of the same family: it wrote the options
 * under `options`, a key the panel never reads.
 *
 * This is the fifth CLI↔runtime divergence of this shape (after `--style=` and
 * `--filter=`), so the fix is the same one those got: a single normaliser every
 * writer funnels through — Ptah\Support\SelectOptions.
 */
class ConfigSelectOptionsTest extends TestCase
{
    private function configure(string ...$columns): array
    {
        $this->artisan('ptah:config', [
            'model' => SelectOptionsStub::class,
            '--column' => $columns,
            '--non-interactive' => true,
        ])->assertExitCode(0);

        return CrudConfig::where('model', ModelKey::canonical(SelectOptionsStub::class))
            ->first()?->config ?? [];
    }

    // ── The normaliser ──────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: mixed, 1: array<string, string>}>
     */
    public static function shapeProvider(): array
    {
        return [
            // The form docs/Commands.md promised.
            'value:label pairs' => [
                'open:Aberto,closed:Fechado',
                ['Aberto' => 'open', 'Fechado' => 'closed'],
            ],
            // The form docs/BaseCrud.md promised — humanised, so `in_progress`
            // does not reach the screen as a snake_case token.
            'bare list' => [
                'open,in_progress,resolved',
                ['Open' => 'open', 'In Progress' => 'in_progress', 'Resolved' => 'resolved'],
            ],
            // A label may itself contain a colon: only the first one splits.
            'label with a colon' => [
                'urgent:Urgente: agora',
                ['Urgente: agora' => 'urgent'],
            ],
            'whitespace is trimmed' => [
                ' open : Aberto , closed : Fechado ',
                ['Aberto' => 'open', 'Fechado' => 'closed'],
            ],
            // Already canonical — must round-trip untouched, so the doctor does
            // not "fix" a config that is already correct.
            'already a map' => [
                ['Aberto' => 'open'],
                ['Aberto' => 'open'],
            ],
            // A host writing config by hand.
            'plain php list' => [
                ['open', 'closed'],
                ['Open' => 'open', 'Closed' => 'closed'],
            ],
            'empty' => ['', []],
            'only separators' => [',,', []],
        ];
    }

    /**
     * @param  array<string, string>  $expected
     */
    #[Test]
    #[DataProvider('shapeProvider')]
    public function the_normaliser_produces_the_map_the_views_read(mixed $raw, array $expected): void
    {
        $this->assertSame($expected, SelectOptions::normalize($raw));
    }

    #[Test]
    public function the_normalised_map_survives_what_the_view_does_to_it(): void
    {
        // The exact expression in _modal-form.blade.php and both branches of
        // _filter-panel.blade.php. Asserting it here means the normaliser is
        // pinned to the reader, not to my reading of the reader.
        $options = SelectOptions::normalize('open:Aberto,closed:Fechado');

        $rendered = array_map(
            static fn (string $l, string $v): array => ['value' => $v, 'label' => $l],
            array_keys($options),
            array_values($options)
        );

        $this->assertSame([
            ['value' => 'open', 'label' => 'Aberto'],
            ['value' => 'closed', 'label' => 'Fechado'],
        ], $rendered);
    }

    #[Test]
    public function the_pipe_separator_is_not_treated_as_a_label_split(): void
    {
        // `badges=` uses `|` with the OPPOSITE order (value|color|label). One
        // separator meaning two different orders in two modifiers of the same
        // command is how this family of bugs starts, so `|` stays literal here.
        // Asserting the VALUE, not the humanised label: what matters is that
        // `a|b` arrived as one option whose value is the literal string, and
        // predicting LabelHumanizer's exact output here would test the wrong
        // thing (and did fail on my first attempt).
        $normalized = SelectOptions::normalize('a|b');

        $this->assertCount(1, $normalized);
        $this->assertSame(['a|b'], array_values($normalized));
    }

    // ── The command ─────────────────────────────────────────────────────────

    #[Test]
    public function the_cli_writes_options_the_runtime_can_render(): void
    {
        $config = $this->configure('status:select:options=open:Aberto,closed:Fechado');

        $column = collect($config['cols'] ?? [])->firstWhere('colsNomeFisico', 'status');

        $this->assertIsArray($column['colsSelect'] ?? null, 'colsSelect precisa ser um array, nao a string crua.');
        $this->assertSame(['Aberto' => 'open', 'Fechado' => 'closed'], $column['colsSelect']);
    }

    #[Test]
    public function configuring_an_existing_column_updates_it_instead_of_duplicating(): void
    {
        // The defect, exactly as hit: configure a field, then configure it again.
        $this->configure('status:text');
        $config = $this->configure('status:select:options=open:Aberto');

        $fields = array_column($config['cols'] ?? [], 'colsNomeFisico');

        $this->assertSame(
            array_values(array_unique($fields)),
            $fields,
            'Rodar --column= duas vezes no mesmo campo criou colunas duplicadas: '.implode(', ', $fields)
        );

        $column = collect($config['cols'])->firstWhere('colsNomeFisico', 'status');
        $this->assertSame('select', $column['colsTipo'], 'A segunda invocacao precisa vencer.');
    }

    #[Test]
    public function an_update_keeps_keys_the_new_definition_did_not_mention(): void
    {
        // A label set through the visual editor must survive a CLI call that
        // only changes the type — merging, not replacing.
        $this->configure('status:text:label=Situação Atual');
        $config = $this->configure('status:select:options=open:Aberto');

        $column = collect($config['cols'])->firstWhere('colsNomeFisico', 'status');

        $this->assertSame('Situação Atual', $column['colsNomeLogico']);
        $this->assertSame('select', $column['colsTipo']);
    }

    #[Test]
    public function the_transient_explicit_key_marker_never_reaches_the_database(): void
    {
        // The mechanism that stops parser defaults clobbering existing values is
        // a bookkeeping key on the parsed array. It must be stripped before the
        // config is persisted: a stray `__explicit` in crud_configs would be
        // read back on the next edit and could survive into the JSON export.
        $config = $this->configure('status:select:options=open:Aberto', 'name:text');

        foreach ($config['cols'] ?? [] as $column) {
            $this->assertArrayNotHasKey(
                ColumnParser::EXPLICIT_KEYS,
                $column,
                'A chave transiente __explicit vazou para o config persistido.'
            );
        }

        $this->assertStringNotContainsString('__explicit', (string) json_encode($config));
    }
}
