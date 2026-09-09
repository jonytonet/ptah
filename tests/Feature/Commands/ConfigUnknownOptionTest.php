<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Support\ModelKey;
use Ptah\Tests\TestCase;

class UnknownOptionStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}

/**
 * An option the DSL does not know is now said out loud.
 *
 * It used to be written into the config verbatim and read by nobody, with the
 * command reporting success. That is how three invented options survived in
 * documented examples for releases:
 *
 *   `sortable=true`     the real switch is the BARE modifier `sortable`;
 *                       with `=true` it stored a dead `sortable` key
 *   `searchable=true`   corresponds to nothing at all — search covers every
 *                       text column automatically (HasCrudQuery::
 *                       getSearchableFields() infers it from colsTipo)
 *   `width=80`          the key is `min_width`, and the docs even say
 *                       "there is no `width=`" while examples used it
 *   `badgeMap=…`        the option is `badges=`, with `|` inside each entry
 *
 * Every one of them looks like it works. The listing comes up, the column is
 * there, and the flag simply never applies — the same silent shape as the
 * badge defect and the `--permission` cast before it.
 *
 * ── What deliberately did NOT change ─────────────────────────────────────
 *
 * The key is still stored. A host may keep a private key in the config and
 * read it from a hook, and taking that away to fix a message would be breaking
 * something to report something. The definition is applied exactly as before;
 * the CLI just says which key it did not recognise.
 */
class ConfigUnknownOptionTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function inventedOptionProvider(): array
    {
        return [
            'sortable as a value' => ['name:text:sortable=true', 'sortable'],
            'width' => ['name:text:width=80', 'width'],
            'badgeMap' => ['status:text:badgeMap=1:success:Ativo', 'badgeMap'],
            'typo' => ['name:text:renderr=money', 'renderr'],
        ];
    }

    #[Test]
    #[DataProvider('inventedOptionProvider')]
    public function an_invented_option_is_named_in_the_output(string $definition, string $key): void
    {
        // Uma substring por chamada: `expectsOutputToContain()` registra uma
        // expectativa Mockery em `doWrite`, e duas substrings da MESMA linha
        // brigam pela mesma chamada — a primeira a casa consome, a segunda
        // falha sozinha. A sugestão em si é asserida em
        // `a_suggestion_reaches_the_output()` e, exaustivamente, na tabela de
        // `ColumnParserTest::suggests_the_option_that_was_probably_meant()`.
        $this->artisan('ptah:config', [
            'model' => UnknownOptionStub::class,
            '--column' => [$definition],
            '--non-interactive' => true,
        ])
            ->expectsOutputToContain("unknown option '{$key}'")
            ->assertExitCode(0);
    }

    #[Test]
    public function a_suggestion_reaches_the_output(): void
    {
        // A ligação entre o palpite e a tela: sem isto, `suggestionFor()`
        // poderia estar perfeito e nunca ser chamado.
        $this->artisan('ptah:config', [
            'model' => UnknownOptionStub::class,
            '--column' => ['name:text:width=80'],
            '--non-interactive' => true,
        ])
            ->expectsOutputToContain('did you mean `min_width=`?')
            ->assertExitCode(0);
    }

    #[Test]
    public function an_option_with_no_plausible_match_is_reported_without_a_guess(): void
    {
        // `searchable` corresponds to nothing, so inventing a neighbour for it
        // would be worse than silence about the neighbour.
        $this->artisan('ptah:config', [
            'model' => UnknownOptionStub::class,
            '--column' => ['name:text:searchable=true'],
            '--non-interactive' => true,
        ])
            ->expectsOutputToContain("unknown option 'searchable'")
            ->doesntExpectOutputToContain('did you mean')
            ->assertExitCode(0);
    }

    #[Test]
    public function the_column_is_still_configured_and_the_key_still_stored(): void
    {
        // O comportamento antigo, preservado de propósito: o aviso é a única
        // mudança. Um host pode guardar chave própria e ler num hook.
        $this->artisan('ptah:config', [
            'model' => UnknownOptionStub::class,
            '--column' => ['name:text:label=Nome:sortable=true'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $config = CrudConfig::where('model', ModelKey::canonical(UnknownOptionStub::class))->first()?->config ?? [];
        $column = $config['cols'][0] ?? [];

        $this->assertSame('Nome', $column['colsNomeLogico'] ?? null, 'A definição tem de ser aplicada como antes.');
        $this->assertTrue($column['sortable'] ?? null, 'E a chave desconhecida continua gravada.');
    }

    #[Test]
    public function the_transient_key_never_reaches_the_database(): void
    {
        // `__unknown` é do mesmo tipo do `__explicit`: existe para o comando
        // ler e tem de morrer antes do save. Uma chave com dois underscores
        // dentro de uma config gravada seria lixo permanente.
        $this->artisan('ptah:config', [
            'model' => UnknownOptionStub::class,
            '--column' => ['name:text:sortable=true:width=80'],
            '--non-interactive' => true,
        ])->assertExitCode(0);

        $stored = (string) json_encode(
            CrudConfig::where('model', ModelKey::canonical(UnknownOptionStub::class))->first()?->config ?? []
        );

        $this->assertStringNotContainsString('__unknown', $stored);
        $this->assertStringNotContainsString('__explicit', $stored);
    }

    #[Test]
    public function a_definition_made_only_of_known_options_says_nothing(): void
    {
        // A contrapartida, e o que mantém o aviso útil: se ele disparasse em
        // definição correta, a primeira coisa que alguém faria é ignorá-lo.
        $this->artisan('ptah:config', [
            'model' => UnknownOptionStub::class,
            '--column' => ['name:text:label=Nome:sortable:min_width=80px:renderer=truncate:max_chars=20'],
            '--non-interactive' => true,
        ])
            ->doesntExpectOutputToContain('unknown option')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_full_config_key_written_out_is_not_unknown(): void
    {
        // A saída de escape deliberada: quem sabe o nome real da chave pode
        // escrevê-lo, e isso não é erro.
        $this->artisan('ptah:config', [
            'model' => UnknownOptionStub::class,
            '--column' => ['name:text:colsMinWidth=120px:totalizadorLabel=Total'],
            '--non-interactive' => true,
        ])
            ->doesntExpectOutputToContain('unknown option')
            ->assertExitCode(0);
    }
}
