<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Base\BaseRepository;
use Ptah\Base\BaseService;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use RuntimeException;

/**
 * A documented call may not disagree with the real signature.
 *
 * `SKILL.md` shipped this for several releases:
 *
 *     $this->service->update($request->validated(), $id)
 *
 * while `BaseService::update()` is `update(int|string $id, array $data)`. The
 * generator's own stubs had it right, and so did `BaseLayer.md`; the document
 * that was wrong is the one an agent reads in order to WRITE code.
 *
 * ── The cause is the API, not the typo ───────────────────────────────────
 *
 * The same class exposes both orders:
 *
 *     update(int|string $id, array $data)
 *     updateQuietly(array $data, int|string $id)
 *
 * Two conventions for the same operation, in one class, is a permanent trap:
 * every doc, example and wrapper written against it has a coin-flip chance of
 * picking the wrong one. One of them already did. It fails loudly — passing an
 * array where `int|string` is expected is a TypeError on the first run — so
 * nothing corrupts; the cost is time and the trust in everything else the
 * document says.
 *
 * ── What this guard checks ───────────────────────────────────────────────
 *
 * Every `->method(...)` call in the docs, against the real parameter list read
 * by reflection. Not the argument NAMES — those are the writer's — but the
 * SHAPE of the first argument: an array where an id belongs, or an id where an
 * array belongs. That is the whole class of error, caught wherever it is
 * written, including in the next document nobody has thought of yet.
 */
class DocSignatureParityTest extends TestCase
{
    /**
     * Where prose that teaches code lives.
     *
     * @return list<string>
     */
    private static function documents(): array
    {
        $roots = [
            __DIR__.'/../../../docs',
            __DIR__.'/../../../resources/boost',
        ];

        $out = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.md')) {
                    $out[] = $file->getPathname();
                }
            }
        }

        sort($out);

        if ($out === []) {
            throw new RuntimeException('DocSignatureParityTest: nenhum .md encontrado — o teste nao teria alvo.');
        }

        return $out;
    }

    /**
     * The methods worth checking, with the shape of their first parameter.
     *
     * Read from the classes, never restated here: a guard that hardcodes the
     * expectation is a second source of truth, which is the very thing it is
     * supposed to prevent.
     *
     * @return array<string, string> method => 'id'|'array'|'other'
     */
    private static function firstParamShapes(): array
    {
        $shapes = [];

        foreach ([BaseService::class, BaseRepository::class] as $class) {
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $params = $method->getParameters();

                if ($params === []) {
                    continue;
                }

                $shape = self::shapeOf($params[0]->getType());

                if ($shape === 'other') {
                    continue;
                }

                $name = $method->getName();

                // The two classes agree on every method they share; if a future
                // change makes them differ, the doc cannot be checked against
                // "the" signature any more and this guard must be told which.
                if (isset($shapes[$name]) && $shapes[$name] !== $shape) {
                    throw new RuntimeException(
                        "DocSignatureParityTest: BaseService::{$name} e BaseRepository::{$name} ".
                        'discordam na forma do primeiro parametro. Alinhe as duas ou ensine este guard.'
                    );
                }

                $shapes[$name] = $shape;
            }
        }

        return $shapes;
    }

    private static function shapeOf(?\ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            return match ($type->getName()) {
                'array' => 'array',
                'int', 'string' => 'id',
                default => 'other',
            };
        }

        if ($type instanceof ReflectionUnionType) {
            $names = array_map(
                static fn (\ReflectionType $t): string => $t instanceof ReflectionNamedType ? $t->getName() : '',
                $type->getTypes()
            );

            sort($names);

            if ($names === ['int', 'string']) {
                return 'id';
            }
        }

        return 'other';
    }

    /**
     * Is this argument expression obviously an array?
     *
     * An allowlist would be stricter, but the shapes an id takes in an example
     * are open-ended (`$id`, `42`, `$product->id`, `$model->getKey()`), while
     * the shapes an array takes are few and recognisable. So this reports only
     * what it is SURE about — a guard that cries wolf on a legitimate example
     * gets switched off.
     */
    private static function looksLikeArray(string $arg): bool
    {
        $arg = trim($arg);

        return $arg !== '' && (
            str_starts_with($arg, '[')
            || str_starts_with($arg, 'array(')
            || str_contains($arg, '->validated()')
            || str_contains($arg, '->all()')
            || str_contains($arg, '->only(')
            || str_contains($arg, '->toArray()')
            || preg_match('/^\$(data|attributes|payload|fields|values|input)\b/', $arg) === 1
        );
    }

    private static function looksLikeId(string $arg): bool
    {
        $arg = trim($arg);

        return preg_match('/^\d+$/', $arg) === 1
            || preg_match('/^\$id\b/', $arg) === 1
            || preg_match('/->(id|getKey\(\))$/', $arg) === 1;
    }

    /**
     * The first argument of a call, by balancing parentheses.
     *
     * A naive split on the first comma would cut `update($id, ['a' => 1, 'b'])`
     * in the wrong place, and an example with a nested call would fool it too.
     */
    private static function firstArgument(string $text, int $openParen): ?string
    {
        $depth = 0;
        $start = $openParen + 1;

        for ($i = $openParen; $i < strlen($text); $i++) {
            $c = $text[$i];

            if ($c === '(' || $c === '[') {
                $depth++;

                continue;
            }

            if ($c === ')' || $c === ']') {
                $depth--;

                if ($depth === 0) {
                    return substr($text, $start, $i - $start);
                }

                continue;
            }

            if ($c === ',' && $depth === 1) {
                return substr($text, $start, $i - $start);
            }
        }

        return null;
    }

    #[Test]
    public function every_documented_call_matches_the_real_signature(): void
    {
        $shapes = self::firstParamShapes();
        $offenders = [];

        foreach (self::documents() as $path) {
            $text = str_replace("\r\n", "\n", (string) file_get_contents($path));
            $lines = explode("\n", $text);

            foreach ($lines as $i => $line) {
                // Calls on a service or a repository, which is what the base
                // classes are reached through in every documented example.
                if (preg_match_all('/->(\w+)\(/', $line, $m, PREG_OFFSET_CAPTURE) === 0) {
                    continue;
                }

                if (! preg_match('/\$(this->)?\w*(service|repository|Service|Repository)\w*->/', $line)) {
                    continue;
                }

                foreach ($m[1] as $k => [$method, $_]) {
                    if (! isset($shapes[$method])) {
                        continue;
                    }

                    $openParen = $m[0][$k][1] + strlen($m[0][$k][0]) - 1;
                    $arg = self::firstArgument($line, $openParen);

                    if ($arg === null || trim($arg) === '') {
                        continue;
                    }

                    $expected = $shapes[$method];

                    $wrong = ($expected === 'id' && self::looksLikeArray($arg))
                        || ($expected === 'array' && self::looksLikeId($arg));

                    if ($wrong) {
                        $offenders[] = sprintf(
                            '  %s:%d  %s() espera %s no 1o argumento, o exemplo passa `%s`',
                            str_replace(dirname(__DIR__, 3).DIRECTORY_SEPARATOR, '', $path),
                            $i + 1,
                            $method,
                            $expected === 'id' ? 'um id (int|string)' : 'um array',
                            trim($arg)
                        );
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Chamada documentada em desacordo com a assinatura real:\n".implode("\n", $offenders)
        );
    }

    #[Test]
    public function the_guard_knows_the_two_methods_that_disagree_with_each_other(): void
    {
        // Sanity, and a statement of the trap. Without this the test above could
        // pass because reflection found nothing worth checking.
        $shapes = self::firstParamShapes();

        $this->assertSame('id', $shapes['update'] ?? null, 'update() deveria receber o id primeiro.');
        $this->assertSame(
            'array',
            $shapes['updateQuietly'] ?? null,
            'updateQuietly() recebe o array primeiro — ordem INVERTIDA em relacao a update(). '.
            'Se isso mudar, o alinhamento aconteceu e este teste deve ser atualizado junto.'
        );
    }

    #[Test]
    public function the_detector_recognises_the_case_that_shipped(): void
    {
        // The exact line SKILL.md carried. A guard nobody has seen fail is a
        // guard nobody knows works.
        $this->assertTrue(self::looksLikeArray('$request->validated()'));
        $this->assertTrue(self::looksLikeArray("['price' => 59.90]"));
        $this->assertTrue(self::looksLikeArray('$data'));

        $this->assertFalse(self::looksLikeArray('$id'));
        $this->assertFalse(self::looksLikeArray('42'));
        $this->assertFalse(self::looksLikeArray('$product->id'));

        $this->assertTrue(self::looksLikeId('42'));
        $this->assertTrue(self::looksLikeId('$id'));
        $this->assertTrue(self::looksLikeId('$product->id'));
        $this->assertFalse(self::looksLikeId('$request->validated()'));
    }

    #[Test]
    public function the_first_argument_is_read_by_balancing_not_by_splitting(): void
    {
        // `update($id, ['a' => 1, 'b' => 2])` would be cut in the wrong place by
        // a split on the first comma.
        $line = '$this->service->update($id, [\'a\' => 1, \'b\' => 2]);';
        $open = strpos($line, 'update(') + strlen('update(') - 1;

        $this->assertSame('$id', trim((string) self::firstArgument($line, $open)));

        $nested = '$this->service->update($product->getKey(), $request->validated());';
        $open = strpos($nested, 'update(') + strlen('update(') - 1;

        $this->assertSame('$product->getKey()', trim((string) self::firstArgument($nested, $open)));
    }
}
