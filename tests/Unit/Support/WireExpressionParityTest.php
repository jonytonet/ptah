<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Livewire\BaseCrud\CrudConfig;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/**
 * A `$wire.something` in a view must name a real public property.
 *
 * `_pagination.blade.php` watched `$wire.page`, and there is no `page`:
 * `WithPagination` keeps the state in `public $paginators = []`
 * (`HandlesPagination.php:10`) and declares neither a `page` property nor a
 * `page()` method. Three mechanisms then line up into a 500:
 *
 *   1. the `$wire` proxy resolves an unknown name through `getFallback()`,
 *      which returns a FUNCTION that calls that method on the server
 *      (`livewire.esm.js:11686`);
 *   2. Alpine's evaluator auto-invokes any function an expression produces
 *      (`runIfTypeOfFunction`, `livewire.esm.js:1882`);
 *   3. `HandleComponents` rejects the call, and the whole Livewire request
 *      dies — not the jump-to-page field, the entire screen.
 *
 * It only showed up past two pages, because that is when the block renders. So
 * the listing worked on day one and broke when the table grew.
 *
 * ── Why the existing guard did not catch it ──────────────────────────────
 *
 * `PaginationClickTest` exists for a defect IDENTICAL in cause — the page
 * buttons used `$set('page', N)` — and its own header explains that
 * `WithPagination` "declares no public `page` property". It reads the
 * `wire:click` the view emits and calls it on a real component, so that a
 * rewrite with a broken expression fails. But it covers `forge-pagination`
 * (the buttons), and the broken `$watch` was in `_pagination` (the field), one
 * file away. Same root, next door, no coverage.
 *
 * This closes the general case instead of that one line: every `$wire.name`
 * across every package view, against the public surface of the component that
 * renders it — read by reflection, never restated here.
 */
class WireExpressionParityTest extends TestCase
{
    private const VIEWS = __DIR__.'/../../../resources/views';

    /**
     * Root views and the component class that renders each.
     *
     * @return array<string, class-string>
     */
    private static function roots(): array
    {
        return [
            'livewire/base-crud/base-crud.blade.php' => BaseCrud::class,
            'livewire/base-crud/crud-config.blade.php' => CrudConfig::class,
            'livewire/ai/ai-chat-widget.blade.php' => AiChatWidget::class,
        ];
    }

    private static function read(string $path): string
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException('WireExpressionParityTest: falha ao ler '.$path);
        }

        return str_replace("\r\n", "\n", $raw);
    }

    /**
     * Which component renders which view, following `@include` from the roots.
     *
     * Read from the views rather than listed by hand: a partial belongs to the
     * component that includes it, and that is a fact the code already states.
     *
     * @return array<string, class-string> relative view path => component
     */
    private static function ownership(): array
    {
        $owners = [];

        foreach (self::roots() as $view => $component) {
            $owners[$view] = $component;

            $pending = [$view];

            while ($pending !== []) {
                $current = array_pop($pending);
                $path = self::VIEWS.'/'.$current;

                if (! is_file($path)) {
                    throw new RuntimeException("WireExpressionParityTest: view raiz ausente — {$current}");
                }

                preg_match_all("/@include\('ptah::([a-z0-9_.-]+)'/i", self::read($path), $m);

                foreach ($m[1] as $dotted) {
                    $included = str_replace('.', '/', $dotted).'.blade.php';

                    if (isset($owners[$included])) {
                        continue;
                    }

                    $owners[$included] = $component;
                    $pending[] = $included;
                }
            }
        }

        return $owners;
    }

    /**
     * The public surface a `$wire.name` may legitimately name.
     *
     * Properties only. A public METHOD is exactly the trap: `$wire.method`
     * without parentheses evaluates to a function, and Alpine calls it.
     *
     * @return list<string>
     */
    private static function publicProperties(string $component): array
    {
        $names = [];

        foreach ((new ReflectionClass($component))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if (! $property->isStatic()) {
                $names[] = $property->getName();
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function publicMethods(string $component): array
    {
        return array_map(
            static fn (ReflectionMethod $m): string => $m->getName(),
            (new ReflectionClass($component))->getMethods(ReflectionMethod::IS_PUBLIC)
        );
    }

    #[Test]
    public function the_include_graph_is_actually_followed(): void
    {
        // Ancora. Se o `@include` mudar de forma, tudo abaixo passaria a vazio
        // — que e o modo de falha que mais se repetiu nestes guards.
        $owners = self::ownership();

        $this->assertGreaterThan(8, count($owners), 'O grafo de includes quase nao achou parciais.');
        $this->assertArrayHasKey(
            'livewire/base-crud/partials/_pagination.blade.php',
            $owners,
            'A parcial de paginacao — onde o defeito estava — tem de estar coberta.'
        );
    }

    #[Test]
    public function every_wire_property_expression_names_a_real_property(): void
    {
        $offenders = [];

        foreach (self::ownership() as $view => $component) {
            $path = self::VIEWS.'/'.$view;

            if (! is_file($path)) {
                continue;
            }

            $source = self::read($path);
            // Comentarios fora: este arquivo e as proprias views explicam o
            // defeito CITANDO `$wire.page`, e um guard que le comentario acusa
            // a nota de rodape que existe para explica-lo.
            $source = preg_replace('!/\*.*?\*!s', '', $source) ?? $source;
            $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? $source;

            $lines = explode("\n", $source);
            $properties = self::publicProperties($component);
            $methods = self::publicMethods($component);

            foreach ($lines as $i => $line) {
                // `$wire.nome` sem parenteses. Com parenteses e uma chamada, que
                // e legitima e nao passa pelo avaliador do Alpine como valor.
                // `(?![A-Za-z0-9_])` antes do lookahead de parentese: sem ele a
                // regex RETROCEDE e casa `sen` em `send(`, porque o lookahead
                // olha para `d` e aprova. Quarenta e dois falsos positivos, todos
                // com a ultima letra comida.
                if (preg_match_all('/\$wire\.([A-Za-z_][A-Za-z0-9_]*)(?![A-Za-z0-9_])(?!\s*\()/', $line, $m) === 0) {
                    continue;
                }

                foreach ($m[1] as $name) {
                    if (in_array($name, $properties, true)) {
                        continue;
                    }

                    $offenders[] = sprintf(
                        '  %s:%d  `$wire.%s` — %s',
                        $view,
                        $i + 1,
                        $name,
                        in_array($name, $methods, true)
                            ? 'e METODO, nao propriedade: sem parenteses o Alpine invoca a funcao e dispara uma chamada ao servidor'
                            : 'nao existe em '.class_basename($component).', e o proxy $wire resolve nome desconhecido como metodo remoto'
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Expressao \$wire que nao nomeia propriedade publica:\n".implode("\n", $offenders)."\n".
            'O Alpine invoca qualquer funcao que a expressao produza, entao isto derruba a requisicao inteira.'
        );
    }

    #[Test]
    public function no_package_view_writes_the_page_expressions_that_already_broke_twice(): void
    {
        // Os literais exatos dos dois defeitos: `$set('page', N)` nos botoes
        // (v1.32.0) e `$wire.page` no campo de salto (v1.34.5). O teste acima
        // ja cobre o segundo pela regra geral; este nomeia os dois para que a
        // mensagem de falha diga o que aconteceu, e para cobrir view que o
        // grafo de includes nao alcance (um componente <x-forge-*>, por
        // exemplo, que nenhum @include cita).
        $forbidden = [
            '$wire.page' => 'nao existe — o estado da paginacao mora em `paginators`',
            "\$set('page'" => 'PublicPropertyNotFoundException: `page` nao e propriedade publica',
            '$set("page"' => 'PublicPropertyNotFoundException: `page` nao e propriedade publica',
            'wire:model="page"' => 'nao ha propriedade `page` para ligar',
        ];

        $offenders = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::VIEWS, \FilesystemIterator::SKIP_DOTS)
        );

        $scanned = 0;

        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $scanned++;
            $source = self::read($file->getPathname());
            $source = preg_replace('/\{\{--.*?--\}\}/s', '', $source) ?? $source;

            foreach ($forbidden as $needle => $why) {
                if (str_contains($source, $needle)) {
                    $offenders[] = sprintf(
                        '  %s  contem `%s` — %s',
                        str_replace(self::VIEWS.DIRECTORY_SEPARATOR, '', $file->getPathname()),
                        $needle,
                        $why
                    );
                }
            }
        }

        $this->assertGreaterThan(20, $scanned, 'Quase nenhuma view varrida — o caminho deve estar errado.');

        $this->assertSame(
            [],
            $offenders,
            "Expressao de paginacao que ja quebrou a tela antes:\n".implode("\n", $offenders)
        );
    }
}
