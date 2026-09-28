<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Every config key a WRITER puts into a screen config must have a RUNTIME
 * reader — the class of defect that shipped three times in September 2026.
 *
 * `actionPermission` and `actionConfirm` were written by the CLI, the wizard
 * and the visual editor, and read by nothing until 1.41.1: every custom action
 * showed to everyone and ran without asking. The editor rewrote `permissions`
 * whole and erased gates it had no field for. WizardKeyReachabilityTest did
 * not catch the first two for two reasons this test fixes:
 *
 *  1. it only looked at ColumnWizard — not ActionWizard, FilterWizard, the
 *     parsers or the editor;
 *  2. it counted ANY file under src/ as a reader, so the parser, the wizard,
 *     the editor and the CLI table formatter — which only write or display the
 *     key — cleared it. Mentioning a key is not reading it.
 *
 * Here a reader is runtime code only: the BaseCrud component and its views,
 * services, HTTP, jobs, support classes that act on a config. Writers,
 * displays, validators and diagnostics (`ptah:check`, `ptah:screen`) are not.
 */
class ConfigKeyReachabilityTest extends TestCase
{
    private const ROOT = __DIR__.'/../../..';

    /** Files and folders that write config keys. */
    private const WRITERS = [
        'src/Commands/Config/Wizards',
        'src/Commands/Config/Parsers',
        'src/Livewire/BaseCrud/CrudConfig.php',
        'src/Generators/CrudConfigGenerator.php',
    ];

    /** Mention a key without acting on it: write, display, validate, diagnose. */
    private const NOT_A_READER = [
        'src/Commands',
        'src/Generators/CrudConfigGenerator.php',
        'src/Livewire/BaseCrud/CrudConfig.php',
        'resources/views/livewire/base-crud/crud-config.blade.php',
        'resources/views/livewire/base-crud/partials/_config-form-preview.blade.php',
        'src/Support/JsonSchemaBuilder.php',
        'src/Support/ScreenSummary.php',
        'src/Support/CliReference.php',
        'src/Support/CrudScreenInspector.php',
        'src/Support/ConfigSchemaValidator.php',
        'src/Services/Crud/ConfigValidator.php',
    ];

    /**
     * Written and knowingly unread. A ratchet: it may only shrink, and
     * `the_exempt_list_is_tight` fails when an entry gains a reader, so a fix
     * has to delete its line.
     *
     * Each is a promise a writer makes that the runtime does not keep. They
     * are frozen here so no NEW one can join, and fixed one release at a time.
     */
    private const EXEMPT = [
        // Consumed inside the writers' own pipeline, not an orphan:
        'actionName' => 'ActionParser::asColumn() turns it into colsNomeLogico before saving',
        'colsWidth' => 'ColumnWizard only reads it as the fallback of a legacy value; it writes colsMinWidth',

        // Frozen by WizardKeyReachabilityTest since 1.34.4 (reasons there):
        'colsRelationDisplayColumn' => 'wizard relationship block; runtime reads colsRelacao/colsRelacaoExibe',
        'colsRelationJoinColumn' => 'wizard relationship block',
        'colsRelationTable' => 'wizard relationship block',
        'colsRendererLink' => 'runtime reads colsRendererLinkTemplate',
        'colsRendererPrefix' => 'never implemented',
        'colsRendererSuffix' => 'never implemented',
        'colsRendererTarget' => 'runtime reads colsRendererLinkNewTab',
        'colsTotal' => 'runtime reads totalizadorType/totalizadorEnabled',
        'colsValidation' => 'runtime reads colsValidations (plural)',
        'colsValidationMessage' => 'no reader',

        // Found by this guard on 28/09/2026 — to be fixed from 1.41.3 on:
        'colsPlaceholder' => 'CLI placeholder= and the wizard write it, the docs teach it, the form never shows it',
        'colsMaskRegex' => 'CLI mask_regex= and an editor field write it; the mask ignores it',
        'actionPosition' => 'wizard and docs offer row/bulk/both; every action renders on the row',
        'colsMaskDecimalPlaces' => 'wizard asks for decimal places and drops the answer',
        'colsRendererFormat' => 'wizard asks for a date format and drops the answer',
        'colsFilterAggregate' => 'FilterWizard writes it; nothing reads it',
        'colsFilterOptions' => 'FilterWizard writes it; nothing reads it',
        'colsFilterRelationField' => 'FilterWizard writes it; nothing reads it',
        'colsFilterWhereHas' => 'FilterWizard writes it; nothing reads it',
        'configEsconderId' => 'the editor saves it; the listing never hides the id for it',
    ];

    private static function read(string $path): string
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException('ConfigKeyReachabilityTest: falha ao ler '.$path);
        }

        return str_replace("\r\n", "\n", $raw);
    }

    /**
     * Source without comments — a comment that explains a defect names the key
     * it is about, and must not count as a writer or a reader. Two passes (see
     * WizardKeyReachabilityTest::code() for why one pattern under `s` deletes
     * the whole file).
     */
    private static function code(string $path): string
    {
        $s = self::read($path);
        $s = preg_replace('~/\*.*?\*/~s', '', $s) ?? $s;
        $s = preg_replace('~\{\{--.*?--\}\}~s', '', $s) ?? $s;

        return preg_replace('~(?<![:"\'])//[^\n]*~', '', $s) ?? $s;
    }

    /** ROOT resolved, with forward slashes — the relative paths below depend on it. */
    private static function root(): string
    {
        return str_replace('\\', '/', (string) realpath(self::ROOT));
    }

    /** @return list<string> */
    private static function files(string $relative): array
    {
        $path = self::root().'/'.$relative;

        if (is_file($path)) {
            return [$path];
        }

        $out = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
                $out[] = str_replace('\\', '/', $f->getPathname());
            }
        }

        sort($out);

        return $out;
    }

    /**
     * `cols*` / `action*` keys the writers put into a config, and the
     * top-level keys the visual editor saves.
     *
     * @return array<string, list<string>> key => writer files
     */
    private static function writtenKeys(): array
    {
        $keys = [];

        foreach (self::WRITERS as $writer) {
            foreach (self::files($writer) as $file) {
                preg_match_all("/'((?:cols|action)[A-Z][A-Za-z]*)'/", self::code($file), $m);
                foreach ($m[1] as $key) {
                    $keys[$key][] = basename($file);
                }
            }
        }

        // Top level of CrudConfig::buildConfigArray(): the keys at its first
        // array depth (12 spaces in).
        $editor = self::code(self::root().'/src/Livewire/BaseCrud/CrudConfig.php');
        if (preg_match('/protected function buildConfigArray\(.*?\n    \}\n/s', $editor, $body)
            && preg_match_all("/^ {12}'([A-Za-z]+)' =>/m", $body[0], $m)) {
            foreach ($m[1] as $key) {
                $keys[$key][] = 'CrudConfig::buildConfigArray';
            }
        }

        if (count($keys) < 60) {
            throw new RuntimeException('ConfigKeyReachabilityTest: quase nenhuma chave extraida ('.count($keys).') — uma regex quebrou.');
        }

        ksort($keys);

        return array_map(fn (array $w) => array_values(array_unique($w)), $keys);
    }

    /** @return array<string, string> relative path => code */
    private static function readers(): array
    {
        static $readers = null;

        if ($readers !== null) {
            return $readers;
        }

        $readers = [];
        foreach (['src', 'resources/views'] as $root) {
            foreach (self::files($root) as $file) {
                $relative = substr($file, strlen(self::root()) + 1);
                foreach (self::NOT_A_READER as $skip) {
                    if (str_starts_with($relative, $skip)) {
                        continue 2;
                    }
                }
                $readers[$relative] = self::code($file);
            }
        }

        if (count($readers) < 100) {
            throw new RuntimeException('ConfigKeyReachabilityTest: poucos arquivos de runtime ('.count($readers).') — o caminho mudou?');
        }

        return $readers;
    }

    /**
     * Word-boundary match, so `colsValidation` is not cleared by the live
     * `colsValidations`.
     */
    private static function hasReader(string $key): bool
    {
        foreach (self::readers() as $code) {
            if (preg_match('/\b'.preg_quote($key, '/').'\b/', $code) === 1) {
                return true;
            }
        }

        return false;
    }

    #[Test]
    public function every_key_a_writer_puts_in_a_config_has_a_runtime_reader(): void
    {
        $orphans = [];

        foreach (self::writtenKeys() as $key => $writers) {
            if (! array_key_exists($key, self::EXEMPT) && ! self::hasReader($key)) {
                $orphans[] = $key.' (written by '.implode(', ', $writers).')';
            }
        }

        $this->assertSame([], $orphans, "Chave gravada que nada no runtime le — o usuario configura e nada acontece:\n  "
            .implode("\n  ", $orphans)
            ."\nLigue a chave ao runtime, ou pare de grava-la (e tire da doc).");
    }

    #[Test]
    public function the_exempt_list_is_tight(): void
    {
        $written = self::writtenKeys();
        $stale = [];

        foreach (array_keys(self::EXEMPT) as $key) {
            if (! isset($written[$key])) {
                $stale[] = $key.' (nenhum escritor grava mais)';
            } elseif (self::hasReader($key)) {
                $stale[] = $key.' (ganhou leitor)';
            }
        }

        $this->assertSame([], $stale, "Remova da EXEMPT — o ratchet so encolhe:\n  ".implode("\n  ", $stale));
    }

    #[Test]
    public function a_writer_file_is_never_counted_as_a_reader(): void
    {
        // A prova do defeito do guard antigo: o escritor mencionava a chave e
        // passava por leitor.
        foreach (array_keys(self::readers()) as $relative) {
            foreach (self::WRITERS as $writer) {
                $this->assertFalse(str_starts_with($relative, $writer), "{$relative} escreve config e nao pode contar como leitor.");
            }
        }
    }
}
