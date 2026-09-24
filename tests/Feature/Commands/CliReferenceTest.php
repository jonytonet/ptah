<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Commands\Config\Parsers\ActionParser;
use Ptah\Commands\Config\Parsers\ColumnParser;
use Ptah\Commands\Config\Parsers\FilterParser;
use Ptah\Commands\Config\Parsers\JoinParser;
use Ptah\Commands\Config\Parsers\StyleParser;
use Ptah\Support\CliReference;
use Ptah\Support\PtahMask;
use Ptah\Tests\TestCase;

/**
 * `ptah:docs` answers from the code, and has to stay cheap.
 *
 * The reason it exists is the token bill: "what is the option for X?" meant
 * reading `docs/BaseCrud.md` (~29k tokens) or `docs/Configuration.md` (~30k).
 * The reference is generated from the parser's own constants, so the only part
 * that CAN drift is the format line of each topic — and every topic's example
 * is run through the real parser here, so a format that lies breaks the suite.
 *
 * The budget test is the other half of the point. A reference that grows until
 * it costs as much as the document it replaces has failed at its one job.
 */
class CliReferenceTest extends TestCase
{
    /**
     * An answer should cost a few hundred tokens, not thirty thousand.
     * ~4 characters per token.
     */
    private const TOKEN_BUDGET_PER_TOPIC = 1000;

    /**
     * @return array<string, array{0: string}>
     */
    public static function topicProvider(): array
    {
        $out = [];

        foreach (CliReference::topics() as $topic) {
            $out[$topic] = [$topic];
        }

        return $out;
    }

    // ── Every example is real ──────────────────────────────────────────────

    #[Test]
    public function the_column_example_parses_and_validates(): void
    {
        $col = (new ColumnParser)->parse(CliReference::topic('column')['example']);

        $this->assertSame('select', $col['colsTipo']);
        $this->assertSame('badge', $col['colsRenderer']);
        $this->assertArrayNotHasKey(ColumnParser::UNKNOWN_KEYS, $col, 'O exemplo usa opcao que o parser nao conhece.');
    }

    #[Test]
    public function the_filter_example_parses(): void
    {
        $filter = (new FilterParser)->parse(CliReference::topic('filter')['example']);

        $this->assertSame('status', $filter['field']);
        $this->assertNotEmpty($filter['colsSelect'] ?? null, 'options= deveria virar colsSelect.');
    }

    #[Test]
    public function the_style_example_parses(): void
    {
        $style = (new StyleParser)->parse(CliReference::topic('style')['example']);

        $this->assertNotEmpty($style);
    }

    #[Test]
    public function the_action_example_parses(): void
    {
        $action = (new ActionParser)->parse(CliReference::topic('action')['example']);

        $this->assertNotEmpty($action);
    }

    #[Test]
    public function the_join_example_parses(): void
    {
        $join = (new JoinParser)->parse(CliReference::topic('join')['example']);

        $this->assertNotEmpty($join);
    }

    #[Test]
    public function the_mask_example_names_a_mask_that_exists(): void
    {
        $col = (new ColumnParser)->parse(CliReference::topic('mask')['example']);

        $this->assertTrue(PtahMask::has($col['colsMask'] ?? ''), 'O exemplo de mascara usa uma mascara inexistente.');
    }

    // ── Generated from the code, so it cannot omit anything ────────────────

    #[Test]
    public function every_option_the_column_parser_knows_is_in_the_reference(): void
    {
        $text = CliReference::render('column', CliReference::topic('column'));

        foreach (array_keys(ColumnParser::KEY_MAP) as $option) {
            $this->assertStringContainsString($option.'→', $text, "A opcao `{$option}=` sumiu da referencia.");
        }

        foreach (ColumnParser::MODIFIERS as $modifier) {
            $this->assertStringContainsString($modifier, $text);
        }
    }

    #[Test]
    public function a_mask_the_host_registered_shows_up(): void
    {
        // O que nenhum documento estatico sabe: as mascaras do proprio host.
        PtahMask::define('placa_mercosul', ['patterns' => ['AAA-9A99']]);

        try {
            $this->assertContains('placa_mercosul', CliReference::topic('mask')['masks']);
        } finally {
            PtahMask::flush();
        }
    }

    #[Test]
    public function every_filter_option_listed_is_actually_read(): void
    {
        // A lista de opcoes do filtro e escrita a mao, entao cada uma tem de
        // MUDAR o que o parser produz — senao a referencia estaria ensinando uma
        // chave morta, exatamente o defeito que ela existe para evitar.
        $parser = new FilterParser;
        $base = $parser->parse('status:text');

        foreach (array_keys(CliReference::topic('filter')['options']) as $option) {
            $value = $option === 'options' ? 'a:A,b:B' : 'x';
            $parsed = $parser->parse("status:text:{$option}={$value}");

            $this->assertNotSame($base, $parsed, "A opcao `{$option}=` do filtro nao muda nada no resultado do parser.");
        }
    }

    // ── It has to stay cheap ───────────────────────────────────────────────

    #[Test]
    #[DataProvider('topicProvider')]
    public function every_topic_fits_the_token_budget(string $topic): void
    {
        $text = CliReference::render($topic, CliReference::topic($topic));
        $tokens = intdiv(strlen($text), 4);

        $this->assertLessThanOrEqual(
            self::TOKEN_BUDGET_PER_TOPIC,
            $tokens,
            "O topico `{$topic}` custa ~{$tokens} tokens — a referencia existe para ser barata."
        );
    }

    // ── The command ────────────────────────────────────────────────────────

    #[Test]
    public function without_a_topic_it_lists_them(): void
    {
        $this->artisan('ptah:docs')
            ->expectsOutputToContain('topics: column, filter, style, action, join, mask')
            ->assertExitCode(0);
    }

    #[Test]
    public function an_unknown_topic_fails_and_lists_the_real_ones(): void
    {
        $this->artisan('ptah:docs', ['topic' => 'colums'])->assertExitCode(1);
    }

    #[Test]
    public function json_output_is_the_topic_itself(): void
    {
        $this->artisan('ptah:docs', ['topic' => 'join', '--json' => true])
            ->expectsOutputToContain('"flag":"--join"')
            ->assertExitCode(0);
    }
}
