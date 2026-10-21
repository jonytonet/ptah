<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Docs;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\ColumnKeys;
use Ptah\Tests\TestCase;
use Ptah\Tests\Unit\Support\ConfigKeyReachabilityTest;
use ReflectionMethod;

/**
 * 1.49.1: the docs as a contract the code must keep.
 *
 * A client's agents follow the docs to the letter, and the docs had drifted:
 * a whole Totalizer section of keys nothing read, a top-level `actions` list
 * the runtime never looks at, options a command does not accept. Names are
 * what a test can hold; ColumnKeysTest covers `cols*`, this covers the rest:
 *
 *  - every `action*` key a doc names is read by the runtime;
 *  - every screen-config JSON example (a block with `cols`) uses top-level
 *    keys the runtime reads, and `cols*`/`action*` keys it knows;
 *  - every `php artisan ptah:*` line names a registered command and options
 *    it accepts.
 *
 * "Read by the runtime" is ConfigKeyReachabilityTest::readers() — writers,
 * commands and validators excluded, comments stripped.
 */
class DocsMatchRuntimeTest extends TestCase
{
    /** A doc names it ON PURPOSE as not read — the sentence around it says so. */
    private const ACTION_DOCUMENTED_AS_NOT_READ = [
        'actionPosition' => 'KnownLimitations.md: listed among keys the runtime does not read',
    ];

    /** Named in Commands.md's "Removed Commands" table and migration block, on purpose. */
    private const REMOVED_COMMANDS = ['ptah:make', 'ptah:make-api'];

    /** Options every Symfony command accepts. */
    private const GLOBAL_OPTIONS = ['help', 'quiet', 'verbose', 'version', 'ansi', 'no-ansi', 'no-interaction', 'env'];

    /** @return array<string, string> file name => contents */
    private static function docs(): array
    {
        $root = dirname(__DIR__, 3);
        $out = [];
        foreach ([...glob("{$root}/docs/*.md") ?: [], "{$root}/README.md"] as $file) {
            $out[basename($file)] = (string) file_get_contents($file);
        }

        return $out;
    }

    /** @return array<string, string> relative path => runtime code */
    private static function readers(): array
    {
        return (new ReflectionMethod(ConfigKeyReachabilityTest::class, 'readers'))->invoke(null);
    }

    private static function runtimeReads(string $key): bool
    {
        foreach (self::readers() as $code) {
            if (str_contains($code, "'{$key}'")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Doc examples are JSON with the usual shorthand: `// comments`, `...`
     * placeholders, trailing commas. Strip those and decode; null if it still
     * is not JSON (a fragment), which is then skipped.
     */
    private static function decodeExample(string $block): ?array
    {
        $s = (string) preg_replace('~(?<![:"])//[^\n]*~', '', $block);
        $s = (string) preg_replace('/^\s*\.\.\.[^\n]*\n/m', '', $s);
        $s = (string) preg_replace('/\[\s*\.\.\.[^\]]*\]/', '[]', $s);
        $s = (string) preg_replace('/\{\s*\.\.\.\s*\}/', '{}', $s);
        $s = (string) preg_replace('/,(\s*[}\]])/', '$1', $s);
        $decoded = json_decode($s, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * A top-level config key read as an INDEX — `['key']` — by runtime code.
     * A bare `'key'` is too loose there: `@section('actions')` in an error
     * view would count as reading a config's `actions`.
     */
    private static function runtimeIndexes(string $key): bool
    {
        foreach (self::readers() as $code) {
            if (preg_match('/\[\s*\''.preg_quote($key, '/').'\'\s*\]/', $code) === 1) {
                return true;
            }
        }

        return false;
    }

    #[Test]
    public function every_action_key_a_doc_names_is_read_by_the_runtime(): void
    {
        $missing = [];
        foreach (self::docs() as $name => $doc) {
            preg_match_all('/\b(action[A-Z][A-Za-z]*)\b/', $doc, $m);
            foreach (array_unique($m[1]) as $key) {
                if (! array_key_exists($key, self::ACTION_DOCUMENTED_AS_NOT_READ) && ! self::runtimeReads($key)) {
                    $missing[] = "{$name}: {$key}";
                }
            }
        }

        $this->assertSame([], $missing, 'A doc cita chave action* que o runtime nao le.');
    }

    #[Test]
    public function screen_config_examples_use_only_keys_the_runtime_reads(): void
    {
        $problems = [];
        $checked = 0;

        foreach (self::docs() as $name => $doc) {
            preg_match_all('/```json\s*\n(.*?)```/s', $doc, $blocks);
            foreach ($blocks[1] as $block) {
                $config = self::decodeExample($block);
                if (is_array($config) && isset($config['colsNomeFisico'])) {
                    $config = ['cols' => [$config]]; // a single-column example
                    $single = true;
                } else {
                    $single = false;
                }
                if (! is_array($config) || ! is_array($config['cols'] ?? null)) {
                    continue;
                }
                $checked++;

                foreach ($single ? [] : array_keys($config) as $top) {
                    if (! self::runtimeIndexes((string) $top)) {
                        $problems[] = "{$name}: top-level '{$top}'";
                    }
                }
                foreach ($config['cols'] as $col) {
                    foreach (array_keys((array) $col) as $key) {
                        $key = (string) $key;
                        if (str_starts_with($key, 'cols') && ! ColumnKeys::isKnown($key)) {
                            $problems[] = "{$name}: cols key '{$key}'";
                        }
                        if (str_starts_with($key, 'action') && ! self::runtimeReads($key)) {
                            $problems[] = "{$name}: action key '{$key}'";
                        }
                    }
                }
            }
        }

        $this->assertGreaterThan(3, $checked, 'Quase nenhum exemplo de config extraido — a regex quebrou?');
        $this->assertSame([], array_values(array_unique($problems)), 'Exemplo de config na doc com chave que o runtime nao le.');
    }

    #[Test]
    public function every_documented_ptah_command_and_option_exists(): void
    {
        $commands = Artisan::all();
        $problems = [];

        foreach (self::docs() as $name => $doc) {
            preg_match_all('/php artisan (ptah:[a-z0-9:-]+)([^\n`]*)/', $doc, $m, PREG_SET_ORDER);
            foreach ($m as [, $command, $rest]) {
                $command = rtrim($command, ':');
                if (in_array($command, self::REMOVED_COMMANDS, true)) {
                    continue;
                }
                if (! isset($commands[$command])) {
                    $problems[] = "{$name}: comando {$command}";

                    continue;
                }
                // Options outside quoted values: --column="a --b" must not count --b.
                $bare = (string) preg_replace('/"[^"]*"|\'[^\']*\'/', '', $rest);
                $bare = (string) preg_replace('/\s#.*$/', '', $bare); // shell comment
                preg_match_all('/(?<![\w-])--([a-z][a-z0-9-]*)/', $bare, $opts);
                foreach (array_unique($opts[1]) as $option) {
                    if (! in_array($option, self::GLOBAL_OPTIONS, true)
                        && ! $commands[$command]->getDefinition()->hasOption($option)) {
                        $problems[] = "{$name}: {$command} --{$option}";
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($problems)), 'A doc cita comando ou opcao que nao existe.');
    }
}
