<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use Illuminate\Database\Eloquent\Model;
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

    /**
     * Methods that exist on the base classes and NOT on an Eloquent model.
     *
     * The discriminator that makes a broader scan safe. `update()` lives on both
     * — `$product->update(['active' => true])` is legitimate Eloquent and
     * appears in BaseLayer.md — so a check that fired on every receiver would
     * report it. `findBy`, `findByIn`, `useIndex`, `updateQuietly` and friends
     * are ptah's alone, so a call to one of those is a call to the base class
     * whatever the receiver is written as, including a bare `$this->` inside a
     * repository example.
     *
     * @return array<string, ReflectionMethod>
     */
    private static function ptahOnlyMethods(): array
    {
        $model = new ReflectionClass(Model::class);
        $out = [];

        foreach ([BaseService::class, BaseRepository::class] as $class) {
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $name = $method->getName();

                if (str_starts_with($name, '__') || $model->hasMethod($name)) {
                    continue;
                }

                $out[$name] ??= $method;
            }
        }

        return $out;
    }

    private static function returnsNull(ReflectionMethod $method): bool
    {
        $type = $method->getReturnType();

        return $type === null || $type->allowsNull();
    }

    #[Test]
    public function no_document_compares_a_non_nullable_return_against_null(): void
    {
        // The one that shipped, and the worst of the four found in this review
        // because it is the FIRST code block of the data-layer skill — the
        // example an agent copies before reading anything else:
        //
        //     public function existsBySku(string $sku): bool
        //     {
        //         return $this->findBy('sku', $sku) !== null;   // always true
        //     }
        //
        // `findBy()` returns a Builder, and a Builder is never null. So
        // `existsBySku()` answered true for every SKU, and the duplicate guard
        // three blocks below — `if ($this->products->existsBySku(...)) throw` —
        // refused every creation with "SKU already registered". It reads
        // correctly, passes review, and is only wrong at runtime.
        //
        // Nullability is the discriminator: `find()` returns `?Model`, so
        // `find($id) !== null` is legitimate and must not be reported.
        $methods = self::ptahOnlyMethods();
        $offenders = [];

        foreach (self::documents() as $path) {
            $lines = explode('
', str_replace('
', '
', (string) file_get_contents($path)));

            foreach ($lines as $i => $line) {
                if (preg_match_all('/->(\w+)\(/', $line, $m) === 0) {
                    continue;
                }

                // A null test, or a null-coalescing fallback, on the result.
                if (preg_match('/(!==|===|!=|==)\s*null|\?\?|is_null\s*\(/', $line) !== 1) {
                    continue;
                }

                foreach ($m[1] as $name) {
                    if (! isset($methods[$name]) || self::returnsNull($methods[$name])) {
                        continue;
                    }

                    $type = $methods[$name]->getReturnType();

                    $offenders[] = sprintf(
                        '  %s:%d  %s() devolve %s e nunca e null — a comparacao e sempre verdadeira',
                        str_replace(dirname(__DIR__, 3).DIRECTORY_SEPARATOR, '', $path),
                        $i + 1,
                        $name,
                        $type instanceof ReflectionNamedType ? $type->getName() : 'um valor nao nulavel'
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Comparacao com null sobre retorno que nunca e null:
'.implode('
', $offenders)
        );
    }

    /**
     * Every reference-table row that makes a CLAIM about a signature, already
     * attributed to the class the section belongs to.
     *
     * The attribution is the whole point. The first version indexed every
     * public base-class method by NAME and compared any table row against it,
     * which reported `create(array $data): Role` — the RoleService row, which
     * is CORRECT — only because `BaseService::create()` returns Model. A method
     * name does not say which class a table is about; the section's
     * `**Namespace:**` line does.
     *
     * @return iterable<array{path: string, line: int, name: string, params: string, return: string|null, method: ReflectionMethod}>
     */
    private static function tableClaims(): iterable
    {
        $ptahOnly = self::ptahOnlyMethods();

        foreach (self::documents() as $path) {
            $lines = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($path)));
            $owner = null;

            foreach ($lines as $i => $line) {
                // Um cabecalho de CLASSE encerra a secao anterior, para uma
                // tabela nao herdar o dono da secao de cima. Nivel 1 ou 2
                // apenas: a primeira versao zerava em qualquer `#`, e o
                // `### Methods` que vem logo abaixo do `**Namespace:**`
                // apagava o dono antes da tabela — o RoleService caia no
                // fallback e `create(): Role` era reportado como Model.
                if (preg_match('/^#{1,2}\s/', $line) === 1) {
                    $owner = null;
                }

                // Sem classe de caractere com contrabarra: `'\\A'` chega na
                // regex como `\A`, que dentro de `[...]` e invalido, e o
                // `preg_match` volta false com aviso. Terceira vez que a
                // escapagem morde nesta sessao; `[^`]+` nao tem esse problema.
                if (preg_match('/\*\*Namespace:\*\*\s*`([^`]+)`/', $line, $ns) === 1) {
                    $owner = class_exists($ns[1]) ? new ReflectionClass($ns[1]) : null;

                    continue;
                }

                if (! str_starts_with(trim($line), '|')) {
                    continue;
                }

                // A classe de caractere desta regex saiu quebrada na primeira
                // versao, e o `preg_match_all` devolvia false — o teste passava
                // A VAZIO, que e justamente o que ele existe para impedir em
                // outro lugar. Um aviso do PHP delatou.
                if (preg_match_all('/`(\w+)\(([^`]*)\)\s*(?::\s*\??([A-Za-z_]+))?`/', $line, $m, PREG_SET_ORDER) === 0) {
                    continue;
                }

                foreach ($m as $row) {
                    $name = $row[1];

                    $method = match (true) {
                        // A secao declara a classe: e ela quem responde.
                        $owner !== null => $owner->hasMethod($name) ? $owner->getMethod($name) : null,
                        // Sem dono declarado, so os metodos exclusivos do ptah
                        // — um `update()` solto pode ser de qualquer coisa.
                        default => $ptahOnly[$name] ?? null,
                    };

                    if ($method === null) {
                        continue;
                    }

                    yield [
                        'path' => str_replace(dirname(__DIR__, 3).DIRECTORY_SEPARATOR, '', $path),
                        'line' => $i + 1,
                        'name' => $name,
                        'params' => $row[2],
                        'return' => ($row[3] ?? '') === '' ? null : $row[3],
                        'method' => $method,
                    ];
                }
            }
        }
    }

    /**
     * The short name, because a table writes `Builder` and reflection answers
     * with the FQCN.
     */
    private static function shortNameOf(string $type): string
    {
        $pos = strrpos($type, '\\');

        return $pos === false ? $type : substr($type, $pos + 1);
    }

    /**
     * The type written for the FIRST parameter of a documented signature, or
     * null when the row does not commit to one.
     *
     * Conservative on purpose: a union written as `int|string $id` is skipped
     * (the real one is a union too, and comparing them textually would be a
     * new source of false alarms), and so is a bare `$column`, which describes
     * rather than asserts.
     */
    private static function firstDeclaredType(string $params): ?string
    {
        $first = explode(',', $params)[0];

        if (preg_match('/^\s*\??([A-Za-z_][A-Za-z0-9_]*)\s+\.{0,3}&?\$\w+/', $first, $m) !== 1) {
            return null;
        }

        return strcasecmp($m[1], 'mixed') === 0 ? null : $m[1];
    }

    /**
     * A documented short name resolved to a real class, or null when it cannot
     * be resolved and therefore cannot be judged.
     *
     * Tried as written, then inside the NAMESPACE OF THE REAL TYPE, which is
     * what separates the two cases this guard has to tell apart:
     *
     *   `findBy(...): Model` vs the real `Illuminate\Database\Eloquent\Builder`
     *      → `Illuminate\Database\Eloquent\Model` exists, is unrelated to
     *        Builder, and the row IS wrong. This was the shipped bug.
     *
     *   `enableTotp(User $user)` vs `Illuminate\Contracts\Auth\Authenticatable`
     *      → `Illuminate\Contracts\Auth\User` does not exist, so the short name
     *        belongs to the host's own model and the table is simplifying, not
     *        lying.
     */
    private static function resolveAgainst(string $documented, string $real): ?string
    {
        if (class_exists($documented) || interface_exists($documented)) {
            return $documented;
        }

        $pos = strrpos($real, '\\');

        if ($pos === false) {
            return null;
        }

        $candidate = substr($real, 0, $pos + 1).$documented;

        return class_exists($candidate) || interface_exists($candidate) ? $candidate : null;
    }

    /**
     * Builtin types, the ones a document cannot be simplifying.
     */
    private const BUILTINS = [
        'int', 'string', 'array', 'bool', 'float', 'iterable',
        'callable', 'object', 'null', 'void', 'never', 'self', 'static',
    ];

    /**
     * Do the documented type and the real one actually DISAGREE?
     *
     * The distinction that makes the parameter check usable. `docs/Modules.md`
     * writes `enableTotp(User $user)` while the method takes
     * `Authenticatable $user` — ten rows of it — and the document is right:
     * the host's User model implements the contract, and naming the concrete
     * class is what makes the table readable. Reporting those would have been
     * ten wrong accusations against one true one.
     *
     * So this answers only when it can PROVE the mismatch: a builtin against a
     * class (`int $roleId` documented as `Role $role`, the real one), or two
     * classes it can resolve and that have no relationship. Two class names it
     * cannot resolve — a short name like `User`, which belongs to the host —
     * are taken as agreeing. A missed divergence is a document that stays
     * wrong; a false one is a guard somebody switches off.
     */
    private static function typesDisagree(string $documented, string $real): bool
    {
        if (strcasecmp($documented, $real) === 0
            || strcasecmp($documented, self::shortNameOf($real)) === 0) {
            return false;
        }

        $docIsBuiltin = in_array(strtolower($documented), self::BUILTINS, true);
        $realIsBuiltin = in_array(strtolower($real), self::BUILTINS, true);

        if ($docIsBuiltin || $realIsBuiltin) {
            // Um builtin nunca e uma simplificacao do outro tipo: `int` e
            // `Role` sao coisas diferentes, e foi essa a divergencia real.
            return true;
        }

        $resolved = self::resolveAgainst($documented, $real);

        if ($resolved === null) {
            // Nome curto de uma classe do host — indecidivel, e o silencio
            // aqui e o que salva as dez linhas do Modules.md.
            return false;
        }

        return ! is_a($resolved, $real, true) && ! is_a($real, $resolved, true);
    }

    #[Test]
    public function a_return_type_written_in_a_reference_table_matches_the_real_one(): void
    {
        // The second half of the drift the report named. The table said
        // `findBy(...)` → "First match by column" and
        // `findByBuilder(Closure $cb)` → "Custom builder, first/get", while the
        // real signatures are `findBy(...): Builder` and
        // `findByBuilder(Builder $query, string $column, ...): Builder`.
        //
        // Only rows that DECLARE a return type are checked: a row describing a
        // method in prose is documentation, not a claim about the type.
        $offenders = [];

        foreach (self::tableClaims() as $claim) {
            if ($claim['return'] === null) {
                continue;
            }

            $type = $claim['method']->getReturnType();

            if (! $type instanceof ReflectionNamedType) {
                continue;
            }

            $real = $type->getName();

            if (! self::typesDisagree($claim['return'], $real)) {
                continue;
            }

            $offenders[] = sprintf(
                '  %s:%d  a tabela diz %s(): %s, o real e %s',
                $claim['path'],
                $claim['line'],
                $claim['name'],
                $claim['return'],
                self::shortNameOf($real)
            );
        }

        $this->assertSame(
            [],
            $offenders,
            "Tabela de referencia com tipo de retorno errado:\n".implode("\n", $offenders)
        );
    }

    #[Test]
    public function a_parameter_type_written_in_a_reference_table_matches_the_real_one(): void
    {
        // The third shape of the same drift, and the one that escaped the guard
        // on the first pass: `docs/Permissions.md` documented
        // `getWithPermissions(Role $role): Role` while the method takes
        // `int $roleId` (src/Services/Permission/RoleService.php:188). The
        // RETURN type was right, so the test above was happy — and anyone
        // following the table would hand it the model where the key goes.
        //
        // Only the FIRST parameter, and only when the row writes a type for
        // it: later parameters are legitimately omitted from a table, and a
        // row that writes just `$column` is describing, not asserting.
        $offenders = [];

        foreach (self::tableClaims() as $claim) {
            $declared = self::firstDeclaredType($claim['params']);
            $parameter = $claim['method']->getParameters()[0] ?? null;

            if ($declared === null || $parameter === null) {
                continue;
            }

            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType) {
                continue;
            }

            $real = $type->getName();

            if (! self::typesDisagree($declared, $real)) {
                continue;
            }

            $offenders[] = sprintf(
                '  %s:%d  a tabela diz %s(%s ...), o primeiro parametro real e %s',
                $claim['path'],
                $claim['line'],
                $claim['name'],
                $declared,
                self::shortNameOf($real)
            );
        }

        $this->assertSame(
            [],
            $offenders,
            "Tabela de referencia com tipo de parametro errado:\n".implode("\n", $offenders)
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
