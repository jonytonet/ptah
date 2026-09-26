<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Commands\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Every config key the interactive wizard writes must be read by something.
 *
 * The wizard asked "Column width (e.g., 120px, 20%, auto)" and stored the
 * answer in `colsWidth`, which nothing reads: the table renders `colsMinWidth`
 * (`_table.blade.php:198`), and the CLI maps `min_width=` to that. So the width
 * you typed was discarded, in silence, with the wizard reporting the column
 * saved.
 *
 * It is the same family as the invented `--column=` option — a key written and
 * read by nobody — and the CLI half of it now warns. The wizard has no
 * unknown-key problem to warn about, because it writes the keys itself; what
 * it needs is this: proof that each one has a reader.
 *
 * ── The debt this froze, and why it is not fixed here ────────────────────
 *
 * `colsWidth` was a rename. The other eleven are not, and each one is a
 * DECISION rather than a typo, so the list below freezes them instead of
 * hiding them. It may only shrink.
 *
 * The relationship block is the one that matters most: answering "Related
 * table name", "Join column" and "Display column" in the wizard writes
 * `colsRelationTable`, `colsRelationJoinColumn` and
 * `colsRelationDisplayColumn`, while the runtime reads `colsRelacao` and
 * `colsRelacaoExibe` — the Portuguese names. So the wizard's relationship
 * configuration does nothing at all, end to end. Mapping it is not a rename
 * either: the wizard asks for a TABLE and a join column, and the runtime wants
 * a relation NAME and a display attribute, which are different questions.
 *
 * The rest, grouped by what each needs:
 *
 *   colsValidation        the runtime key is `colsValidations`, PLURAL. Close
 *   colsValidationMessage enough to look right in a diff, and the message has
 *                         no reader at all.
 *   colsRendererPrefix    renderer options that were never implemented.
 *   colsRendererSuffix
 *   colsRendererLink      the real key is `colsRendererLinkTemplate`.
 *   colsRendererTarget    `colsRendererLinkNewTab` is what the renderer reads.
 *   colsTotal             the totalizer reads `totalizadorType` and
 *                         `totalizadorEnabled`.
 *
 * None of this affects `--column=`, which goes through ColumnParser and its
 * KEY_MAP. It is the INTERACTIVE path — `ptah:config` with no
 * `--non-interactive` — that asks these questions and drops the answers.
 */
class WizardKeyReachabilityTest extends TestCase
{
    private const WIZARD = __DIR__.'/../../../../src/Commands/Config/Wizards/ColumnWizard.php';

    /**
     * Keys the wizard writes that are knowingly unread.
     *
     * A ratchet: it may only shrink. Every entry has its reason in the class
     * docblock, and adding one means a new question whose answer is thrown
     * away — which is the thing this test exists to stop.
     */
    private const EXEMPT = [
        'colsRelationDisplayColumn',
        'colsRelationJoinColumn',
        'colsRelationTable',
        'colsRendererLink',
        'colsRendererPrefix',
        'colsRendererSuffix',
        'colsRendererTarget',
        'colsTotal',
        'colsValidation',
        'colsValidationMessage',
    ];

    private static function read(string $path): string
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException('WizardKeyReachabilityTest: falha ao ler '.$path);
        }

        return str_replace("\r\n", "\n", $raw);
    }

    /**
     * The `'colsXxx' =>` keys the wizard puts into the column it returns.
     *
     * @return list<string>
     */
    private static function writtenKeys(): array
    {
        preg_match_all("/'(cols[A-Za-z]+)'\s*=>/", self::code(), $m);

        $keys = array_values(array_unique($m[1] ?? []));

        if (count($keys) < 5) {
            throw new RuntimeException('WizardKeyReachabilityTest: quase nenhuma chave extraida — a regex quebrou.');
        }

        sort($keys);

        return $keys;
    }

    /**
     * The wizard's source with the comments taken out.
     *
     * Stripping is not optional here: the comment explaining the defect NAMES
     * `colsWidth`, so a guard that reads comments reports its own footnote.
     *
     * Two passes, and the reason is a trap this file walked into first: doing
     * both alternatives in ONE pattern under the `s` modifier deletes the whole
     * FILE from the first `//` onwards, because `s` makes `.` cross newlines.
     * The extraction then found nothing and the negative assertion below passed
     * on an empty string — which is why `writtenKeys()` has a floor on how few
     * keys it will accept. (And writing that pattern out here, block-comment
     * terminator included, is what made this very docblock end early.)
     */
    private static function code(): string
    {
        $source = self::read(self::WIZARD);

        $source = preg_replace('!/\*.*?\*/!s', '', $source) ?? $source;

        return preg_replace('!//[^\n]*!', '', $source) ?? $source;
    }

    /**
     * Files that MENTION a key without reading it.
     *
     * `JsonSchemaBuilder` describes a column shape that the runtime does not
     * implement — it names `colsSortable`, `colsSearchable` and `colsRelation`,
     * none of which exist anywhere else, and nothing rejects a config for
     * disagreeing with it. Counting it as a reader would let the wizard write a
     * key whose only corroboration is another document.
     */
    private const NOT_A_READER = [
        'JsonSchemaBuilder.php',
    ];

    /**
     * Does anything outside the wizard actually read this key?
     *
     * Word-boundary, not substring: the wizard writes the SINGULAR
     * `colsValidation` while the runtime reads `colsValidations`, and a
     * substring search called that a match — the guard cleared a dead key
     * because a live key contains its name.
     */
    private static function hasReader(string $key): bool
    {
        $roots = [
            __DIR__.'/../../../../src',
            __DIR__.'/../../../../resources/views',
        ];

        foreach ($roots as $root) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $file) {
                if (! $file->isFile() || realpath($file->getPathname()) === realpath(self::WIZARD)) {
                    continue;
                }

                if (! preg_match('/\.(php|blade\.php)$/', $file->getFilename())) {
                    continue;
                }

                if (in_array($file->getFilename(), self::NOT_A_READER, true)) {
                    continue;
                }

                if (preg_match('/'.preg_quote($key, '/').'\b/', self::read($file->getPathname())) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    #[Test]
    public function every_key_the_wizard_writes_has_a_reader(): void
    {
        $orphans = [];

        foreach (self::writtenKeys() as $key) {
            if (in_array($key, self::EXEMPT, true)) {
                continue;
            }

            if (! self::hasReader($key)) {
                $orphans[] = $key;
            }
        }

        $this->assertSame(
            [],
            $orphans,
            'O wizard grava chave que ninguem le: '.implode(', ', $orphans)."\n".
            'A resposta do usuario e descartada em silencio, com o wizard dizendo que salvou.'
        );
    }

    #[Test]
    public function the_width_answer_reaches_the_key_the_table_renders(): void
    {
        // O defeito concreto, pinado pelo nome: `colsWidth` nao pode voltar, e
        // `colsMinWidth` e a chave que `_table.blade.php` desenha.
        $code = self::code();

        $this->assertMatchesRegularExpression(
            "/'colsMinWidth'\s*=>/",
            $code,
            'A largura respondida no wizard tem de ir para colsMinWidth.'
        );

        $this->assertDoesNotMatchRegularExpression(
            "/'colsWidth'\s*=>/",
            $code,
            'colsWidth nao e lido por nada — gravar nele descarta a resposta.'
        );
    }

    #[Test]
    public function the_ratchet_has_not_grown(): void
    {
        // A contagem congelada. Ela e o unico jeito de a isencao nao virar o
        // lugar onde defeito novo vai morar: subir este numero e uma decisao
        // visivel no diff, e nao um item a mais numa lista longa.
        $this->assertLessThanOrEqual(
            11,
            count(self::EXEMPT),
            'A lista de isencoes so pode diminuir — o wizard nao deveria ganhar pergunta cuja resposta e descartada.'
        );
    }

    #[Test]
    public function the_exemption_list_only_holds_keys_that_are_really_unread(): void
    {
        // Se alguem implementar o default value, este teste manda apagar a
        // isencao em vez de deixa-la envelhecer como verdade.
        foreach (self::EXEMPT as $key) {
            $this->assertFalse(
                self::hasReader($key),
                "`{$key}` ja tem leitor — remova a isencao em WizardKeyReachabilityTest."
            );
        }
    }
}
