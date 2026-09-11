<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;
use RuntimeException;

class JumpStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name'];
}

/**
 * The jump-to-page field took the whole screen down past two pages.
 *
 * `_pagination.blade.php` watched `$wire.page`, and `page` is not a property:
 * `WithPagination` keeps its state in `public $paginators = []`. Livewire's
 * `$wire` proxy answers an unknown name with a FUNCTION that calls that method
 * on the server, and Alpine's evaluator invokes any function an expression
 * produces — so the `$watch` fired a request for a method that does not exist
 * and `HandleComponents` killed the whole update with
 * `MethodNotFoundException`. Not the field: the screen.
 *
 * The block only renders when `lastPage() > 2`, which is why a listing works on
 * day one and breaks when the table grows. That is also why this test seeds
 * three pages: with two, the defect is not on the page at all.
 *
 * `WireExpressionParityTest` now covers the general rule statically. This one
 * covers the half a static scan cannot: that the expressions the view emits are
 * a real property and a real method, taken off the SHIPPED html and used
 * against a real component — the idiom `PaginationClickTest` introduced for the
 * identical defect in the buttons.
 */
class PaginationJumpTest extends TestCase
{
    private const PER_PAGE = 5;

    protected function setUp(): void
    {
        parent::setUp();

        CrudConfig::create([
            'model' => JumpStub::class,
            'route' => '',
            'config' => [
                'crud' => JumpStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsVisibleList' => true, 'colsGravar' => true],
                ],
                'permissions' => [],
            ],
        ]);
    }

    /**
     * Nao chamar de `seed()`: o TestCase do Testbench tem um `seed()` publico, e
     * baixar a visibilidade e fatal antes de qualquer teste rodar. Terceira
     * colisao desta familia na sessao, depois de `run()` duas vezes.
     */
    private function seedRows(int $rows): void
    {
        for ($i = 1; $i <= $rows; $i++) {
            JumpStub::create(['name' => sprintf('Row %02d', $i)]);
        }
    }

    private function crud(): Testable
    {
        return Livewire::test(BaseCrud::class, ['model' => JumpStub::class])
            ->set('perPage', self::PER_PAGE);
    }

    /**
     * The Alpine expression the shipped view puts in `x-init`.
     */
    private function watchExpression(string $html): string
    {
        if (preg_match('/x-init="\$watch\(&#039;([^&]+)&#039;/', $html, $m) !== 1
            && preg_match("/x-init=\"\\\$watch\\('([^']+)'/", $html, $m) !== 1) {
            throw new RuntimeException('Nao achei o $watch do campo de salto no HTML renderizado.');
        }

        return $m[1];
    }

    /**
     * The seeded rows visible in a rendered page.
     *
     * @return list<string>
     */
    private function rowsOn(string $html): array
    {
        preg_match_all('/Row \d\d/', $html, $m);

        return array_values(array_unique($m[0]));
    }

    #[Test]
    public function the_field_only_appears_past_two_pages(): void
    {
        // O gatilho do defeito, pinado: com duas paginas o bloco nem existe, e
        // um teste que semeasse menos passaria sem tocar no codigo quebrado.
        $this->seedRows(self::PER_PAGE * 2);

        $this->assertStringNotContainsString('pagination_goto', $this->crud()->html());
        $this->assertStringNotContainsString('x-init="$watch', $this->crud()->html());
    }

    #[Test]
    public function the_watched_expression_names_a_real_public_property(): void
    {
        $this->seedRows(self::PER_PAGE * 3);

        $expression = $this->watchExpression($this->crud()->html());

        $this->assertStringStartsWith('$wire.', $expression);

        $property = explode('.', $expression)[1] ?? '';

        // Lido do componente real, nao de uma lista escrita aqui.
        $public = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass(BaseCrud::class))->getProperties(\ReflectionProperty::IS_PUBLIC)
        );

        $this->assertContains(
            $property,
            $public,
            "`\$wire.{$property}` nao e propriedade publica do BaseCrud. O proxy \$wire resolve ".
            'nome desconhecido como metodo remoto, e o Alpine invoca a funcao — a requisicao inteira morre.'
        );
    }

    #[Test]
    public function the_watched_path_is_the_paginator_state_for_this_page_name(): void
    {
        $this->seedRows(self::PER_PAGE * 3);

        $this->assertSame(
            '$wire.paginators.page',
            $this->watchExpression($this->crud()->html()),
            'O estado da paginacao mora em `paginators[<nome da pagina>]`.'
        );
    }

    #[Test]
    public function the_field_action_moves_the_listing_on_a_real_component(): void
    {
        // A outra metade: a expressao de acao do campo, chamada de verdade.
        $this->seedRows(self::PER_PAGE * 3);

        $component = $this->crud();
        $html = $component->html();

        // `gotoPage` COM o nome da pagina, que e o que a view emite.
        $this->assertMatchesRegularExpression(
            '/gotoPage\(pg, &#039;page&#039;\)|gotoPage\(pg, \'page\'\)/',
            $html,
            'O campo tem de chamar gotoPage passando o nome da pagina.'
        );

        $page1 = $this->rowsOn($html);

        $this->assertCount(self::PER_PAGE, $page1, 'A primeira pagina deveria trazer uma pagina cheia.');

        $component->call('gotoPage', 3, 'page')->assertOk();

        $page3 = $this->rowsOn($component->html());

        $this->assertCount(self::PER_PAGE, $page3, 'A terceira pagina nao carregou.');
        // Sem assumir a ordenacao padrao: o que prova o salto e as duas paginas
        // trazerem registros DIFERENTES, nao um nome especifico estar na
        // primeira. A primeira versao disto fixou `Row 01` e caiu porque a
        // listagem nao ordena por inclusao.
        $this->assertSame(
            [],
            array_intersect($page1, $page3),
            'Pagina 1 e pagina 3 trouxeram os mesmos registros — o salto nao aconteceu.'
        );
    }

    #[Test]
    public function the_rendered_page_never_carries_the_expression_that_broke(): void
    {
        $this->seedRows(self::PER_PAGE * 3);

        $this->assertStringNotContainsString(
            '$wire.page',
            $this->crud()->html(),
            'Voltou a expressao que devolve 500 em toda listagem com mais de duas paginas.'
        );
    }
}
