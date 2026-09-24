<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\LogErrorReader;
use Ptah\Tests\TestCase;
use RuntimeException;

/**
 * `ptah:last-error` reads a REAL Laravel log entry, not an imitation of one.
 *
 * Every entry here is written by the framework's own logger — Monolog's line
 * formatter, with the stack trace embedded in the JSON context under
 * `exception` — because a parser tested against a hand-typed sample passes
 * against the sample and fails against the log. The point of the command is
 * the token bill: `tail -300 laravel.log` is thousands of tokens of vendor
 * frames; this is the exception, the SQL, and the application's own frames.
 */
class LastErrorCommandTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logFile = sys_get_temp_dir().'/ptah-last-error-'.uniqid().'.log';

        config()->set('logging.channels.ptah_test', [
            'driver' => 'single',
            'path' => $this->logFile,
            'level' => 'debug',
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->logFile);

        parent::tearDown();
    }

    private function logException(\Throwable $e, array $context = []): void
    {
        Log::channel('ptah_test')->error($e->getMessage(), $context + ['exception' => $e]);
    }

    private function aQueryException(): QueryException
    {
        try {
            DB::select('select * from a_table_that_does_not_exist');
        } catch (QueryException $e) {
            return $e;
        }

        $this->fail('Esperava uma QueryException.');
    }

    #[Test]
    public function it_reads_the_exception_class_message_and_sql_out_of_a_real_entry(): void
    {
        $this->logException($this->aQueryException());

        $error = LogErrorReader::lastError($this->logFile);
        $this->assertNotNull($error);
        $this->assertSame(QueryException::class, $error['exception']);
        $this->assertSame('ERROR', $error['level']);
        $this->assertStringContainsString('a_table_that_does_not_exist', (string) $error['sql'], 'O SQL da QueryException deveria sair separado.');
        $this->assertStringNotContainsString('SQL:', $error['message'], 'O SQL nao deve ficar duplicado dentro da mensagem.');
        $this->assertStringContainsString('no such table', $error['message']);
    }

    #[Test]
    public function the_sql_is_extracted_in_the_laravel_11_format_too(): void
    {
        // Laravel 11 nao tem o trecho "Database:"; o 12+ tem. Os dois precisam sair.
        file_put_contents($this->logFile, "[2026-09-23 10:00:00] production.ERROR: SQLSTATE[42S02]: Base table not found (Connection: mysql, SQL: select * from `x`) {\"exception\":\"[object] (Illuminate\\\\Database\\\\QueryException(code: 42S02): boom at /srv/app/vendor/laravel/framework/src/Illuminate/Database/Connection.php:825)\"} \n");

        $error = LogErrorReader::lastError($this->logFile);

        $this->assertSame('select * from `x`', $error['sql']);
        $this->assertSame('SQLSTATE[42S02]: Base table not found', $error['message']);
        $this->assertSame('production', $error['env']);
    }

    #[Test]
    public function only_application_frames_are_shown_and_the_rest_are_counted(): void
    {
        $this->logException($this->aQueryException());

        $error = LogErrorReader::lastError($this->logFile);

        // Nada de vendor/laravel nos frames mostrados — esse e o ralo de token.
        foreach ($error['frames'] as $frame) {
            $this->assertStringNotContainsString('vendor/laravel', $frame['file'], 'Frame de vendor vazou para a saida.');
        }

        $this->assertGreaterThan(0, $error['hidden_frames'], 'Os frames do framework deveriam ser contados, nao descartados em silencio.');
    }

    #[Test]
    public function it_takes_the_las_t_error_and_skips_later_non_error_entries(): void
    {
        $this->logException(new RuntimeException('primeiro erro'));
        $this->logException(new RuntimeException('ultimo erro'));
        Log::channel('ptah_test')->info('uma linha de info depois do erro');

        $error = LogErrorReader::lastError($this->logFile);

        $this->assertSame('ultimo erro', $error['message']);
        $this->assertSame(RuntimeException::class, $error['exception']);
    }

    #[Test]
    public function the_ptah_error_id_is_carried_when_present(): void
    {
        // O id que a pagina 500 mostra ao usuario, e que o suporte procura.
        $this->logException(new RuntimeException('com id'), ['ptahErrorId' => 'abc123def456']);

        $this->assertSame('abc123def456', LogErrorReader::lastError($this->logFile)['error_id']);
    }

    #[Test]
    public function a_log_with_no_error_returns_nothing(): void
    {
        Log::channel('ptah_test')->info('tudo certo');
        Log::channel('ptah_test')->warning('so um aviso');

        $this->assertNull(LogErrorReader::lastError($this->logFile));
    }

    #[Test]
    public function the_command_prints_a_compact_answer(): void
    {
        $this->logException($this->aQueryException());

        $this->artisan('ptah:last-error', ['--file' => $this->logFile])
            ->expectsOutputToContain('Illuminate\\Database\\QueryException')
            ->assertExitCode(0);
    }

    #[Test]
    public function the_compact_answer_is_far_smaller_than_the_raw_entry(): void
    {
        // O motivo de o comando existir, medido: a resposta tem de custar uma
        // fracao da entrada crua que o agente leria com `tail`.
        $this->logException($this->aQueryException());

        $raw = (string) file_get_contents($this->logFile);
        $compact = (string) json_encode(LogErrorReader::lastError($this->logFile));

        $this->assertLessThan(
            strlen($raw) / 3,
            strlen($compact),
            'A resposta compacta deveria custar menos de um terco da entrada crua.'
        );
    }

    #[Test]
    public function json_output_is_parseable(): void
    {
        $this->logException(new RuntimeException('para json'));

        $this->artisan('ptah:last-error', ['--file' => $this->logFile, '--json' => true])
            ->expectsOutputToContain('"exception":"RuntimeException"')
            ->assertExitCode(0);
    }
}
