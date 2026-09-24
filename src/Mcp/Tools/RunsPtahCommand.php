<?php

declare(strict_types=1);

namespace Ptah\Mcp\Tools;

use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * An MCP tool that answers by running one ptah command and returning its text.
 *
 * Registered into Laravel Boost's MCP server (see PtahServiceProvider) only
 * when `laravel/mcp` is installed, so an agent connected to Boost sees the
 * ptah tools next to Boost's own and calls them directly — no shell, and no
 * need for a skill to tell it the commands exist.
 *
 * The command's plain-text output IS the answer: those commands were written
 * to be short and to say what is left to do. A tool that re-implemented them
 * would drift from the CLI; this cannot.
 *
 * Every class under this namespace extends Laravel\Mcp\Server\Tool and must
 * never be referenced by `::class` outside a `class_exists()` guard — the
 * package does not require laravel/mcp.
 */
abstract class RunsPtahCommand extends Tool
{
    /**
     * @param  array<string, mixed>  $args
     */
    protected function run(string $command, array $args = []): Response
    {
        $output = new BufferedOutput;

        try {
            $code = Artisan::call($command, $args, $output);
        } catch (\Throwable $e) {
            return Response::error("{$command} failed: ".$e->getMessage());
        }

        $text = trim($output->fetch());

        // Um exit != 0 aqui costuma ser a resposta (ptah:check com uma tela
        // falhando), nao uma falha da tool; so vira erro quando nao ha texto.
        if ($code !== 0) {
            return $text !== ''
                ? Response::text($text."\n(exit {$code})")
                : Response::error("{$command} exited with code {$code}.");
        }

        return Response::text($text !== '' ? $text : '(no output)');
    }
}
