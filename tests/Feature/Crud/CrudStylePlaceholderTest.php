<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\StyleTemplate;
use Ptah\Tests\TestCase;

class TagStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}

/**
 * `{{column}}` inside a declarative style, resolved from the row.
 *
 * The case: a Tags table where each tag carries its own colour, and the badge
 * has to be that colour. The old model needed one `{field, condition, value,
 * style}` rule per known colour, which does not scale past a handful; the
 * workaround was a presenter returning pre-coloured HTML through
 * `colsMetodoCustom` + `colsMetodoRaw`.
 *
 * Half of this class is about what the substitution must REFUSE. The value
 * comes from a database column, which in a CRUD means a user typed it, and it
 * lands inside a `style` attribute. Blade escapes the attribute so quotes
 * cannot break out — but a CSS value is its own language, and
 * `url(https://tracker/x)` in a `background-color` is a request to a third
 * party from an authenticated page.
 */
class CrudStylePlaceholderTest extends TestCase
{
    /**
     * @param  array<int, array<string, mixed>>  $styles
     * @param  array<string, mixed>  $extraCol
     */
    private function makeConfig(array $styles = [], array $extraCol = []): void
    {
        CrudConfig::updateOrCreate(
            ['model' => TagStub::class, 'route' => ''],
            ['config' => [
                'crud' => TagStub::class,
                'cols' => [
                    array_merge([
                        'colsNomeFisico' => 'name',
                        'colsNomeLogico' => 'Nome',
                        'colsTipo' => 'text',
                        'colsVisibleList' => true,
                    ], $extraCol),
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Cor', 'colsTipo' => 'text', 'colsVisibleList' => true],
                ],
                'contitionStyles' => $styles,
                'permissions' => [],
            ]]
        );
    }

    private function render(string $colour): string
    {
        TagStub::create(['name' => 'urgente', 'status' => $colour]);

        return Livewire::test(BaseCrud::class, ['model' => TagStub::class])->html();
    }

    /**
     * Every `style="..."` value in the document, and nothing else.
     *
     * Asserting the absence of a value across the WHOLE html is unsound here,
     * and two data sets proved it: the colour column is a visible column, so
     * `url(https://rastreador.test/x)` legitimately appears as the cell's TEXT —
     * escaped, inert, and correct. The question is only ever whether it reached
     * a style attribute.
     *
     * @return list<string>
     */
    private function styleAttributes(string $html): array
    {
        preg_match_all('/\sstyle="([^"]*)"/', $html, $m);

        return $m[1];
    }

    // ── O caso que motivou ────────────────────────────────────────────────

    #[Test]
    public function a_row_is_painted_with_a_colour_that_comes_from_its_own_column(): void
    {
        // One rule, every colour — which is the whole point.
        $this->makeConfig([[
            'field' => 'status',
            'condition' => 'always',
            'style' => 'background-color: {{status}}1a; color: {{status}}',
        ]]);

        $html = $this->render('#e11d48');

        $this->assertStringContainsString('background-color: #e11d481a', $html);
        $this->assertStringContainsString('color: #e11d48', $html);
    }

    #[Test]
    public function a_cell_is_painted_the_same_way(): void
    {
        // And this is the one the Tags case really wants: the colour belongs to
        // the badge, not to the whole row.
        $this->makeConfig([], [
            'colsCellStyle' => 'background-color: {{status}}1a; color: {{status}}; padding: 2px 8px; border-radius: 6px',
        ]);

        $html = $this->render('#0ea5e9');

        $this->assertStringContainsString('background-color: #0ea5e91a', $html);
    }

    #[Test]
    public function the_always_condition_needs_no_value_to_compare(): void
    {
        // Expressing "every row" with the old vocabulary meant `!=` against a
        // value the column never holds — a lie that happens to work.
        $this->makeConfig([[
            'field' => 'status',
            'condition' => 'always',
            'style' => 'font-weight: 700',
        ]]);

        $this->assertStringContainsString('font-weight: 700', $this->render('#111111'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function alwaysAliasProvider(): array
    {
        return [
            'asterisco' => ['*'],
            'always' => ['always'],
            'any' => ['any'],
        ];
    }

    #[Test]
    #[DataProvider('alwaysAliasProvider')]
    public function the_always_condition_answers_to_its_aliases(string $alias): void
    {
        $this->makeConfig([[
            'field' => 'status',
            'condition' => $alias,
            'style' => 'outline: 1px solid red',
        ]]);

        $this->assertStringContainsString('outline: 1px solid red', $this->render('#222222'));
    }

    // ── O que a substituicao recusa ───────────────────────────────────────

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafeValueProvider(): array
    {
        return [
            // A request to a third party, from an authenticated page.
            'url()' => ['url(https://rastreador.test/x)'],
            // Closes the declaration and opens another.
            'ponto e virgula' => ['#fff; background-image: url(https://x.test/p)'],
            'expression' => ['expression(alert(1))'],
            'var()' => ['var(--ptah-primary)'],
            'com espaco' => ['red blue'],
            'com parenteses' => ['rgb(1,2,3)'],
            'aspas' => ['"#fff"'],
            'barra' => ['/**/'],
            'hex curto invalido' => ['#ff'],
            'hex longo invalido' => ['#1234567890'],
            'nao hex' => ['#gggggg'],
        ];
    }

    #[Test]
    #[DataProvider('unsafeValueProvider')]
    public function an_unsafe_value_drops_the_whole_style(string $value): void
    {
        // The whole style, not just the placeholder: a half-substituted
        // declaration is a rule the author never wrote, and applying it
        // silently is worse than applying nothing.
        $this->makeConfig([[
            'field' => 'status',
            'condition' => 'always',
            'style' => 'background-color: {{status}}',
        ]]);

        $styles = $this->styleAttributes($this->render($value));

        // Sem esta ancora o laco abaixo passa a vazio. A tabela sempre emite
        // `style=""` na <tr>, entao um resultado vazio significaria que a
        // renderizacao mudou, nao que o valor foi recusado.
        $this->assertNotEmpty($styles, 'Nenhum atributo style no HTML — a asserticao nao teria alvo.');

        foreach ($styles as $style) {
            $this->assertStringNotContainsString(
                $value,
                $style,
                "O valor inseguro chegou a um atributo style: {$style}"
            );
        }
    }

    #[Test]
    public function a_css_keyword_is_allowed_because_it_cannot_say_anything_else(): void
    {
        // Letters only: no `:`, `;`, `(`, `)`, `/` or space, so there is nothing
        // it could be but a keyword. Refusing these would mean a Tags table
        // storing `teal` had to store `#008080` instead for no safety gain.
        $this->makeConfig([[
            'field' => 'status',
            'condition' => 'always',
            'style' => 'color: {{status}}',
        ]]);

        $this->assertStringContainsString('color: rebeccapurple', $this->render('rebeccapurple'));
    }

    #[Test]
    public function a_missing_column_drops_the_style_rather_than_emptying_it(): void
    {
        $this->makeConfig([[
            'field' => 'status',
            'condition' => 'always',
            'style' => 'color: {{nao_existe}}',
        ]]);

        $styles = $this->styleAttributes($this->render('#333333'));

        $this->assertNotEmpty($styles);

        foreach ($styles as $style) {
            $this->assertStringNotContainsString('color:', $style);
        }
    }

    #[Test]
    public function an_empty_column_drops_the_style_and_lets_the_next_rule_win(): void
    {
        // A tag with no colour set should still get whatever plain rule follows,
        // which is why an unresolvable placeholder continues the loop instead of
        // returning early.
        $this->makeConfig([
            ['field' => 'status', 'condition' => 'always', 'style' => 'color: {{status}}'],
            ['field' => 'name', 'condition' => '==', 'value' => 'urgente', 'style' => 'font-style: italic'],
        ]);

        $html = $this->render('');

        $this->assertStringContainsString('font-style: italic', $html);
    }

    // ── A unidade ─────────────────────────────────────────────────────────

    #[Test]
    public function a_style_with_no_placeholder_is_returned_untouched(): void
    {
        // Every style written before this existed takes this path, so it must
        // cost nothing and change nothing.
        $style = 'background: #fee; font-weight: 600';

        $this->assertFalse(StyleTemplate::hasPlaceholders($style));
        $this->assertSame($style, StyleTemplate::resolve($style, ['status' => 'lixo; url(x)']));
    }

    #[Test]
    public function whitespace_inside_the_braces_is_tolerated(): void
    {
        $this->assertSame(
            'color: #fff',
            StyleTemplate::resolve('color: {{ status }}', ['status' => '#fff'])
        );
    }

    #[Test]
    public function every_placeholder_in_one_style_is_substituted(): void
    {
        $this->assertSame(
            'color: #111; border-color: teal',
            StyleTemplate::resolve('color: {{a}}; border-color: {{b}}', ['a' => '#111', 'b' => 'teal'])
        );
    }

    #[Test]
    public function one_bad_placeholder_condemns_the_style_even_if_the_others_are_fine(): void
    {
        $this->assertNull(
            StyleTemplate::resolve(
                'color: {{a}}; border-color: {{b}}',
                ['a' => '#111', 'b' => 'url(https://x.test)']
            )
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function hexShapeProvider(): array
    {
        return [
            'tres' => ['#abc'],
            'quatro' => ['#abcd'],
            'seis' => ['#a1b2c3'],
            'oito' => ['#a1b2c3d4'],
            'maiuscula' => ['#ABCDEF'],
        ];
    }

    #[Test]
    #[DataProvider('hexShapeProvider')]
    public function every_valid_hex_shape_is_accepted(string $hex): void
    {
        $this->assertSame("color: {$hex}", StyleTemplate::resolve('color: {{c}}', ['c' => $hex]));
    }

    #[Test]
    public function a_non_scalar_value_is_refused(): void
    {
        // An array or an object stringifies to something nobody meant.
        $this->assertNull(StyleTemplate::resolve('color: {{c}}', ['c' => ['#fff']]));
        $this->assertNull(StyleTemplate::resolve('color: {{c}}', ['c' => null]));
        $this->assertNull(StyleTemplate::resolve('color: {{c}}', ['c' => true]));
    }
}
