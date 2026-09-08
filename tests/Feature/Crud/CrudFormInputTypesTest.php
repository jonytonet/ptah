<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Enums\CrudConfigEnums;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\PtahMask;
use Ptah\Tests\TestCase;
use RuntimeException;

class InputTypeStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}

/**
 * The form's input types, and the masked input.
 *
 * Two gaps closed here, and one of them had been sitting in plain sight: the
 * LISTING has had a `color` renderer for a long time — it draws the swatch — and
 * the FORM had no way to enter one, so a Tags screen made the user type the hex
 * by hand. `datetime` was in `CrudConfigEnums::COLUMN_TYPES` the whole time and fell
 * through to `text`, so a column configured as datetime never had the native
 * control either.
 */
class CrudFormInputTypesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PtahMask::flush();
        config(['ptah-masks' => []]);
    }

    protected function tearDown(): void
    {
        PtahMask::flush();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $col
     */
    private function openForm(array $col): string
    {
        CrudConfig::updateOrCreate(
            ['model' => InputTypeStub::class, 'route' => ''],
            ['config' => [
                'crud' => InputTypeStub::class,
                'cols' => [array_merge([
                    'colsNomeFisico' => 'name',
                    'colsNomeLogico' => 'Campo',
                    'colsVisibleList' => true,
                    // getFormCols() filtra por colsGravar (e colsEditableForm,
                    // que ja e true por omissao). `colsVisibleForm` nao existe
                    // — a primeira versao deste arranjo usava essa chave e o
                    // formulario renderizava zero campos, entao vinte
                    // asserticoes falhavam por motivo errado.
                    'colsGravar' => true,
                ], $col)],
                'permissions' => [],
            ]]
        );

        $html = Livewire::test(BaseCrud::class, ['model' => InputTypeStub::class])
            ->call('openCreate')
            ->html();

        if (! str_contains($html, 'crud-form-fields')) {
            throw new RuntimeException('O formulario nao renderizou — nada abaixo teria alvo.');
        }

        return $html;
    }

    // ── Tipos HTML5 ───────────────────────────────────────────────────────

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function html5TypeProvider(): array
    {
        return [
            'date' => ['date', 'date'],
            // Estava na lista de tipos e caia no default `text`.
            'datetime' => ['datetime', 'datetime-local'],
            'datetime-local' => ['datetime-local', 'datetime-local'],
            'time' => ['time', 'time'],
            'range' => ['range', 'range'],
            'email' => ['email', 'email'],
            'url' => ['url', 'url'],
            'tel' => ['tel', 'tel'],
            'number' => ['number', 'number'],
        ];
    }

    #[Test]
    #[DataProvider('html5TypeProvider')]
    public function a_column_type_reaches_the_input_as_that_type(string $colsTipo, string $expected): void
    {
        $html = $this->openForm(['colsTipo' => $colsTipo]);

        $this->assertMatchesRegularExpression(
            '/<input[^>]*type="'.preg_quote($expected, '/').'"/',
            $html,
            "colsTipo `{$colsTipo}` deveria render um input type=\"{$expected}\"."
        );
    }

    #[Test]
    public function every_new_type_is_accepted_by_the_configuration(): void
    {
        // A type the form renders but the config rejects is unreachable through
        // the editor and the CLI.
        foreach (['color', 'time', 'datetime-local', 'range', 'email', 'url', 'tel'] as $type) {
            $this->assertContains($type, CrudConfigEnums::COLUMN_TYPES, "`{$type}` falta em TYPES.");
        }
    }

    #[Test]
    public function an_unknown_type_still_falls_back_to_text(): void
    {
        // A config written for a future version must not render nothing.
        $html = $this->openForm(['colsTipo' => 'holograma']);

        $this->assertMatchesRegularExpression('/<input[^>]*type="text"/', $html);
    }

    // ── Color ─────────────────────────────────────────────────────────────

    #[Test]
    public function the_color_type_renders_the_native_picker(): void
    {
        $html = $this->openForm(['colsTipo' => 'color']);

        $this->assertMatchesRegularExpression('/<input[^>]*type="color"/', $html);
    }

    #[Test]
    public function the_color_type_also_renders_an_editable_hex_field(): void
    {
        // The native picker is a small square that does not say WHICH colour is
        // in it, and in a Tags screen the hex is the data — it goes into CSS.
        // Someone who already has the hex pastes it instead of hunting in a
        // gradient.
        $html = $this->openForm(['colsTipo' => 'color']);

        $this->assertStringContainsString('placeholder="#3b82f6"', $html);
        $this->assertStringContainsString('maxlength="7"', $html);
        $this->assertStringContainsString('x-bind:value="hex"', $html);
    }

    #[Test]
    public function the_color_picker_is_named_for_a_screen_reader(): void
    {
        // The visible <label> points at the text field; a control with no name
        // of its own is invisible to a screen reader.
        $html = $this->openForm(['colsTipo' => 'color']);

        $this->assertMatchesRegularExpression('/<input[^>]*type="color"[^>]*aria-label="[^"]+"/s', $html);
    }

    #[Test]
    public function an_optional_colour_can_be_cleared(): void
    {
        // Without this there is no way back to "no colour": the native picker
        // always holds one.
        $html = $this->openForm(['colsTipo' => 'color']);

        $this->assertStringContainsString('ptah-c-color_clear', $html);
        $this->assertStringContainsString('clear()', $html);
    }

    #[Test]
    public function a_required_colour_has_no_clear_button(): void
    {
        // Offering a control that empties a field the form will then refuse is
        // an invitation to a validation error.
        $html = $this->openForm(['colsTipo' => 'color', 'colsRequired' => true]);

        $this->assertStringNotContainsString('ptah-c-color_clear', $html);
    }

    #[Test]
    public function the_colour_field_chrome_is_tokenised(): void
    {
        $css = (string) file_get_contents(__DIR__.'/../../../resources/css/ptah-components.css');
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        foreach (['ptah-c-color_pick', 'ptah-c-color_clear'] as $class) {
            $this->assertMatchesRegularExpression(
                '/\.'.preg_quote($class, '/').'[^{]*\{[^}]*var\(--ptah-/',
                $css,
                "`.{$class}` precisa pintar por token."
            );
        }
    }

    // ── Máscara no input ──────────────────────────────────────────────────

    #[Test]
    public function a_registered_mask_gets_the_pattern_driven_input(): void
    {
        PtahMask::define('cpf', ['pattern' => '000.000.000-00', 'store' => 'digits']);

        $html = $this->openForm(['colsTipo' => 'text', 'colsMask' => 'cpf']);

        $this->assertStringContainsString('placeholder="000.000.000-00"', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('maxlength="14"', $html);
        $this->assertStringContainsString('@input="onInput($event)"', $html);
    }

    #[Test]
    public function an_unregistered_mask_falls_back_to_a_plain_input(): void
    {
        // Exactly what `colsMask: "cpf"` did before the registry existed, so a
        // host that has not defined its masks yet sees no change.
        $html = $this->openForm(['colsTipo' => 'text', 'colsMask' => 'cpf']);

        $this->assertStringNotContainsString('ptah-mask-in-name', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="text"/', $html);
    }

    #[Test]
    public function the_patterns_reach_the_browser_in_capacity_order(): void
    {
        // The client picks the smallest pattern that fits by walking the list
        // and keeping the last match, so the ORDER is the rule — largest first.
        PtahMask::define('tel', [
            'pattern' => ['(00) 0000-0000', '(00) 00000-0000'],
            'store' => 'digits',
        ]);

        $html = $this->openForm(['colsTipo' => 'text', 'colsMask' => 'tel']);

        // Afirmado por POSICAO, e nao por regex: o `@js` escapa as aspas como
        // " dentro de um JSON.parse, e duas versoes da regex erraram a
        // contagem de barras enquanto a ordem na tela estava certa. Comparar
        // posicoes diz o que importa — qual vem primeiro — sem depender de como
        // o Blade escapou.
        $maior = strpos($html, '(00) 00000-0000');
        $menor = strpos($html, '(00) 0000-0000');

        $this->assertIsInt($maior, 'O pattern de nove digitos nao chegou ao HTML.');
        $this->assertIsInt($menor, 'O pattern de oito digitos nao chegou ao HTML.');
        $this->assertLessThan(
            $menor,
            $maior,
            'Os patterns precisam chegar do maior para o menor: o cliente escolhe o ultimo que couber.'
        );
    }

    #[Test]
    public function a_store_only_mask_does_not_take_over_the_input(): void
    {
        // `digits` and `decimal` have no display shape: there is nothing to
        // draw for "just the digits", so the field stays a plain input and the
        // normalisation happens on save.
        $html = $this->openForm(['colsTipo' => 'text', 'colsMask' => 'digits']);

        $this->assertStringNotContainsString('ptah-mask-in-name', $html);
    }

    #[Test]
    public function an_existing_value_opens_already_formatted(): void
    {
        // The column holds the stripped value, so without formatting on the
        // server an edit opens with eleven bare digits in the field.
        PtahMask::define('cpf', ['pattern' => '000.000.000-00', 'store' => 'digits']);

        InputTypeStub::create(['name' => '05546530952']);

        CrudConfig::updateOrCreate(
            ['model' => InputTypeStub::class, 'route' => ''],
            ['config' => [
                'crud' => InputTypeStub::class,
                'cols' => [[
                    'colsNomeFisico' => 'name',
                    'colsNomeLogico' => 'CPF',
                    'colsTipo' => 'text',
                    'colsMask' => 'cpf',
                    'colsVisibleList' => true,
                    'colsGravar' => true,
                ]],
                'permissions' => [],
            ]]
        );

        $row = InputTypeStub::first();

        $html = Livewire::test(BaseCrud::class, ['model' => InputTypeStub::class])
            ->call('openEdit', $row->id)
            ->html();

        $this->assertStringContainsString('055.465.309-52', $html);
    }
}
