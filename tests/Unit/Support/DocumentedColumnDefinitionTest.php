<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\ColumnParser;
use Ptah\Enums\CrudConfigEnums;
use Ptah\Services\Validation\ConfigSchemaValidator;
use Ptah\Tests\TestCase;
use RuntimeException;
use Throwable;

/**
 * Every `--column="…"` a document teaches must survive the parser and the
 * validator.
 *
 * Four reports in a row have been the same shape: a document asserting
 * something the code contradicts. Two of them were about this exact option.
 *
 *   `ptah-scaffold/SKILL.md:79`   is_active:badge:label=Status:badgeMap=…
 *   `ptah-development/SKILL.md`   is_active:select:…:badges=…  (no options=)
 *
 * The first invents a syntax that never existed — `badge` is not a `colsTipo`
 * and `badgeMap` is not an option — while `KnownLimitations.md:212` documents
 * that exact pattern as WRONG. One skill taught what another document marks as
 * an error, and the scaffold skill is the one an agent reads FIRST.
 *
 * The second was valid syntax that the validator refused, because
 * `colsTipo: select` demands `colsSelect` and `badges=` does not create it.
 *
 * Neither was reachable by any test, because tests configure columns in PHP
 * arrays and the documents write CLI strings. This closes that gap: the string
 * a reader copies is the string that gets parsed here.
 *
 * ── On the deliberately-wrong examples ───────────────────────────────────
 *
 * `KnownLimitations.md` exists to show what NOT to write, so a definition
 * introduced as wrong is skipped. The window is three lines, the same shape
 * `GuidelinesCssParityTest` needed — a one-line lookbehind reported correct
 * text three times before that.
 */
class DocumentedColumnDefinitionTest extends TestCase
{
    /**
     * Words that mark the following example as a counter-example.
     */
    private const NEGATIONS = [
        'wrong', '❌', 'do not', 'don\'t', 'never', 'errado', 'não use',
        'incorrect', 'anti-pattern',
        // `Validation-System.md` mostra a saida de erro do proprio validador,
        // e para isso precisa de uma definicao invalida.
        'error example', 'exemplo de erro',
        // A saida do proprio comando quando encontra a opcao inventada.
        'unknown option',
    ];

    /**
     * @return list<string>
     */
    private static function documents(): array
    {
        $out = [];

        foreach ([__DIR__.'/../../../docs', __DIR__.'/../../../resources/boost'] as $root) {
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
            throw new RuntimeException('DocumentedColumnDefinitionTest: nenhum .md encontrado — o teste nao teria alvo.');
        }

        return $out;
    }

    /**
     * Is the example introduced as something not to do?
     *
     * @param  list<string>  $lines
     */
    private static function negatedNearby(array $lines, int $index): bool
    {
        // Duas linhas ADIANTE tambem, e por um motivo concreto: o exemplo que
        // documenta o proprio aviso do CLI escreve a definicao errada numa
        // linha e a mensagem de aviso na seguinte, entao a marca de
        // contra-exemplo vem DEPOIS da definicao. Este guard reportou a nota
        // que explica o defeito que ele existe para pegar.
        for ($i = max(0, $index - 3); $i <= $index + 2; $i++) {
            $haystack = mb_strtolower($lines[$i] ?? '');

            foreach (self::NEGATIONS as $needle) {
                if (str_contains($haystack, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Every documented column definition, with where it is written.
     *
     * @return list<array{path: string, line: int, definition: string}>
     */
    private static function definitions(): array
    {
        $out = [];

        foreach (self::documents() as $path) {
            $lines = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($path)));

            foreach ($lines as $i => $line) {
                if (preg_match_all('/--column=(?:"([^"]+)"|\'([^\']+)\')/', $line, $m, PREG_SET_ORDER) === 0) {
                    continue;
                }

                if (self::negatedNearby($lines, $i)) {
                    continue;
                }

                foreach ($m as $match) {
                    $definition = $match[1] !== '' ? $match[1] : ($match[2] ?? '');

                    // `--column="..."` como reticências é um marcador de
                    // "preencha aqui", não uma definição.
                    if ($definition === '' || str_contains($definition, '...') || str_contains($definition, '…')) {
                        continue;
                    }

                    $out[] = [
                        'path' => str_replace(dirname(__DIR__, 3).DIRECTORY_SEPARATOR, '', $path),
                        'line' => $i + 1,
                        'definition' => $definition,
                    ];
                }
            }
        }

        return $out;
    }

    #[Test]
    public function the_documents_do_teach_column_definitions(): void
    {
        // Sem esta âncora, tudo abaixo passaria a vazio no dia em que a regex
        // deixasse de casar — que é exatamente como o teste de tabela do
        // DocSignatureParityTest passou verde estando quebrado.
        $this->assertGreaterThan(
            15,
            count(self::definitions()),
            'A extração de `--column="…"` dos documentos não achou quase nada — a regex deve ter quebrado.'
        );
    }

    #[Test]
    public function every_documented_column_definition_has_a_valid_type(): void
    {
        // O caso do ptah-scaffold: `is_active:badge:…`. `badge` é um RENDERER,
        // não um colsTipo, e o ConfigSchemaValidator recusa a coluna inteira.
        $offenders = [];

        foreach (self::definitions() as $case) {
            $type = explode(':', $case['definition'])[1] ?? 'text';

            // Um `key=value` na segunda posição significa que o tipo foi
            // omitido, e o parser assume `text`.
            if (str_contains($type, '=')) {
                continue;
            }

            if (! in_array($type, CrudConfigEnums::COLUMN_TYPES, true)) {
                $offenders[] = sprintf(
                    '  %s:%d  colsTipo "%s" nao existe — em `%s`',
                    $case['path'],
                    $case['line'],
                    $type,
                    $case['definition']
                );
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Definicao documentada com colsTipo inexistente:\n".implode("\n", $offenders)
        );
    }

    #[Test]
    public function no_documented_column_definition_uses_an_option_that_does_not_exist(): void
    {
        // A quinta forma da mesma família, e a que o aviso do CLI tornou
        // verificável: uma opção que o DSL não conhece era gravada na config e
        // lida por ninguém, com o comando reportando sucesso. Foi assim que
        // `sortable=true` (o interruptor é o modificador NU), `searchable=true`
        // (não existe chave de busca configurável) e `badgeMap=` (a opção é
        // `badges=`) sobreviveram em exemplos por releases.
        //
        // O vocabulário vem de `ColumnParser::knowsKey()`, nunca reescrito
        // aqui: um guard que repete a expectativa é uma segunda fonte de
        // verdade, que é justamente o que ele existe para impedir.
        $offenders = [];

        foreach (self::definitions() as $case) {
            $parts = explode(':', $case['definition']);

            // O campo e o tipo não são opções.
            foreach (array_slice($parts, 2) as $part) {
                if (! str_contains($part, '=')) {
                    continue;
                }

                [$key] = explode('=', $part, 2);

                if (ColumnParser::knowsKey($key)) {
                    continue;
                }

                // Um fragmento de VALOR também chega aqui — `badges=a|b,c|d`
                // não tem ':' mas `options=open:Aberto` tem, e "Aberto" não é
                // uma opção. O tokenizer é quem sabe a diferença, então
                // pergunte a ele em vez de adivinhar.
                if (! self::isTokenizedAsOption($case['definition'], $key)) {
                    continue;
                }

                $suggestion = ColumnParser::suggestionFor($key);

                $offenders[] = sprintf(
                    '  %s:%d  opcao `%s=` nao existe%s — em `%s`',
                    $case['path'],
                    $case['line'],
                    $key,
                    $suggestion === null ? '' : ", talvez `{$suggestion}`",
                    $case['definition']
                );
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Definicao documentada com opcao inexistente:\n".implode("\n", $offenders)
        );
    }

    /**
     * Did the tokenizer read this `key=` as an option, or as part of a value?
     *
     * `options=open:Aberto` is one token, and splitting the definition on ':'
     * would offer `Aberto` as if it were an option name. The parser already
     * settles this, and asking it is the only way not to re-implement it here.
     */
    private static function isTokenizedAsOption(string $definition, string $key): bool
    {
        $parsed = (new ColumnParser)->parse($definition);

        return in_array($key, $parsed[ColumnParser::UNKNOWN_KEYS] ?? [], true);
    }

    #[Test]
    public function every_documented_column_definition_survives_parser_and_validator(): void
    {
        // O caso do select + badges: sintaxe válida que o validador recusava
        // com "requires colsSelect to be configured". Um exemplo que não passa
        // pelo validador não é um exemplo, é uma armadilha.
        $parser = new ColumnParser;
        $validator = new ConfigSchemaValidator;
        $offenders = [];

        foreach (self::definitions() as $case) {
            try {
                $col = $parser->parse($case['definition']);
                unset($col[ColumnParser::EXPLICIT_KEYS]);

                $validator->validate(['crud' => 'Doc', 'cols' => [$col]], 'Doc');
            } catch (Throwable $e) {
                // Uma definicao INCREMENTAL e legitima: `--column=` faz upsert,
                // e ConfigCommand::upsertColumn() mescla apenas as chaves que a
                // definicao setou. Por isso
                // `--column="status:select:readonly"` numa config por rota
                // (BaseCrud.md:2852) esta correto — o colsSelect ja esta na
                // coluna que ele altera. Validar o fragmento isolado nao
                // distingue os dois casos, entao a dependencia que falta nao
                // conta; o que conta e o valor que nao existe.
                if (str_contains($e->getMessage(), 'to be configured')) {
                    continue;
                }

                $offenders[] = sprintf(
                    '  %s:%d  %s — em `%s`',
                    $case['path'],
                    $case['line'],
                    $e->getMessage(),
                    $case['definition']
                );
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Definicao documentada que o proprio pacote recusa:\n".implode("\n", $offenders)
        );
    }
}
