<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Services\AI;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The AI turn preparation raises the execution limit for slow local providers.
 * Unguarded, that call did the OPPOSITE of its intent in CLI: `php artisan`,
 * queue workers and PHPUnit run with max_execution_time = 0 (unlimited), so
 * set_time_limit(300) imposed a 300s ceiling on the whole process — which is
 * what killed full test runs mid-suite with "Maximum execution time of 300
 * seconds exceeded" pointing at unrelated files (the timer started in the AI
 * service and expired inside a docblock parser or a query grammar).
 *
 * A behavioral test would have to actually run a Prism turn against a
 * provider, so this pins the guard textually — the shape of the mistake, not
 * a value: any set_time_limit in src/ must be preceded by a SAPI check.
 */
class AiChatServiceTimeLimitTest extends TestCase
{
    #[Test]
    public function every_set_time_limit_call_in_src_is_guarded_against_cli(): void
    {
        $root = dirname(__DIR__, 4).'/src';
        $offenders = [];

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        /** @var \SplFileInfo $file */
        foreach ($rii as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $raw = file_get_contents($file->getPathname());

            if ($raw === false) {
                throw new RuntimeException('AiChatServiceTimeLimitTest: falha ao ler '.$file->getPathname());
            }

            // Comments MUST go before scanning. This guard's own explanatory
            // comment quotes set_time_limit(300) — leaving comments in makes
            // the check trip on its own prose, which is the single most
            // recurrent way a guard in this codebase goes wrong.
            $source = self::stripComments($raw);

            if (! str_contains($source, 'set_time_limit(')) {
                continue;
            }

            // The guard may sit a few lines above the call (a computed limit,
            // a comment block); scan the whole enclosing region instead of the
            // single previous line.
            foreach (explode('set_time_limit(', $source) as $i => $chunk) {
                if ($i === 0) {
                    continue;
                }

                $before = substr(implode('set_time_limit(', array_slice(explode('set_time_limit(', $source), 0, $i)), -600);

                if (! str_contains($before, "PHP_SAPI !== 'cli'") && ! str_contains($before, 'PHP_SAPI === \'cli\'')) {
                    $offenders[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "set_time_limit() sem guarda de SAPI em:\n".implode("\n", $offenders)."\n\n".
            'Em CLI (artisan, queue:work, PHPUnit) max_execution_time e 0 = ilimitado, '.
            'entao set_time_limit IMPOE um teto no processo inteiro em vez de estender '.
            'algo — e o estouro acontece em outro arquivo qualquer, minutos depois.'
        );
    }

    /** Drops comments and string literals so only real code is scanned. */
    private static function stripComments(string $source): string
    {
        $out = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $out .= $token[1];

                continue;
            }

            $out .= $token;
        }

        return $out;
    }

    #[Test]
    public function the_cli_execution_limit_is_still_unlimited_in_this_process(): void
    {
        // Sanity anchor for the premise above: if a future PHP/PHPUnit config
        // starts capping CLI, the reasoning in the guard needs revisiting.
        $this->assertSame(
            '0',
            (string) ini_get('max_execution_time'),
            'A premissa da guarda e que CLI roda sem limite; este ambiente diverge.'
        );
    }
}
