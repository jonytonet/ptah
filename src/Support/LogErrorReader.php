<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * The last error in a Laravel log, reduced to what it takes to act on it.
 *
 * When something breaks, the reflex is `tail -300 storage/logs/laravel.log`,
 * and for an agent that is thousands of tokens — most of them vendor frames of
 * the framework's own pipeline, identical in every error. What decides the next
 * step is the exception, its message, the SQL when there is one, the file and
 * line it was thrown at, and the few frames that are the APPLICATION's: under
 * `app/` or in this package. This keeps those and counts the rest.
 *
 * Laravel writes the stack trace INSIDE the JSON context, under `exception`, so
 * an entry is everything from one `[timestamp] env.LEVEL:` header to the next —
 * not a line. And a production log easily runs to hundreds of megabytes, so
 * only its tail is read.
 */
final class LogErrorReader
{
    /**
     * Levels that count as an error.
     */
    public const ERROR_LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /**
     * How much of the end of the file is read, in bytes. An entry with a long
     * trace is a few tens of KB; 1 MB leaves a wide margin without loading a
     * multi-GB log.
     */
    private const TAIL_BYTES = 1_048_576;

    private const HEADER = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2})?)\] ([\w.-]+)\.([A-Z]+): /m';

    /**
     * The most recently written `*.log` in a directory — `laravel.log` for the
     * single driver, `laravel-YYYY-MM-DD.log` for the daily one.
     */
    public static function latestLogFile(string $dir): ?string
    {
        $files = glob(rtrim($dir, '/\\').'/*.log') ?: [];

        if ($files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        return $files[0];
    }

    /**
     * The last error entry in a log file, parsed, or null when there is none.
     *
     * @param  string|null  $basePath  stripped from paths so they read `app/…`
     * @return array{timestamp: string, env: string, level: string, exception: string|null, code: string|null, message: string, sql: string|null, file: string|null, line: int|null, frames: list<array{n: int, file: string, line: int, call: string}>, hidden_frames: int, error_id: string|null}|null
     */
    public static function lastError(string $path, int $maxFrames = 6, ?string $basePath = null): ?array
    {
        $text = self::tail($path);

        if ($text === '') {
            return null;
        }

        if (preg_match_all(self::HEADER, $text, $headers, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) === 0) {
            return null;
        }

        // Da ultima entrada para tras, a primeira de nivel de erro.
        for ($i = count($headers) - 1; $i >= 0; $i--) {
            $level = $headers[$i][3][0];

            if (! in_array($level, self::ERROR_LEVELS, true)) {
                continue;
            }

            $start = $headers[$i][0][1];
            $end = isset($headers[$i + 1]) ? $headers[$i + 1][0][1] : strlen($text);

            return self::parseEntry(
                substr($text, $start, $end - $start),
                $headers[$i][1][0],
                $headers[$i][2][0],
                $level,
                $maxFrames,
                $basePath,
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseEntry(string $entry, string $timestamp, string $env, string $level, int $maxFrames, ?string $basePath): array
    {
        // A mensagem e o que vem depois do cabecalho, ate o contexto JSON.
        $firstLine = strtok($entry, "\n") ?: '';
        $message = (string) preg_replace('/^\[[^\]]+\] [\w.-]+\.[A-Z]+: /', '', $firstLine);
        $contextAt = strpos($message, ' {"');
        if ($contextAt !== false) {
            $message = substr($message, 0, $contextAt);
        }

        $exception = null;
        $code = null;
        $file = null;
        $line = null;

        // "[object] (Class(code: X): mensagem at /caminho/arquivo.php:123)"
        if (preg_match('/\[object\] \(([A-Za-z0-9_\\\\]+)\(code: ([^)]*)\): .*? at (.+?):(\d+)\)/s', $entry, $m) === 1) {
            $exception = stripslashes($m[1]);
            $code = $m[2];
            $file = self::relative(stripslashes($m[3]), $basePath);
            $line = (int) $m[4];
        }

        $sql = null;
        // "(Connection: x, SQL: …)" no Laravel 11; "(Connection: x, Database: y, SQL: …)" no 12+.
        if (preg_match('/\s*\(Connection: [^,]+,(?: Database: .*?,)? SQL: (.+)\)\s*$/', $message, $s, PREG_OFFSET_CAPTURE) === 1) {
            $sql = $s[1][0];
            $message = trim(substr($message, 0, $s[0][1]));
        }

        $errorId = null;
        if (preg_match('/"ptahErrorId":"([^"]+)"/', $entry, $e) === 1) {
            $errorId = $e[1];
        }

        $frames = [];
        $hidden = 0;

        if (preg_match_all('/^#(\d+) (.+?)\((\d+)\): (.+)$/m', stripslashes($entry), $fs, PREG_SET_ORDER) > 0) {
            foreach ($fs as $f) {
                $framePath = str_replace('\\', '/', $f[2]);

                // Da aplicacao: sob app/ ou dentro do ptah. O resto e o pipeline
                // do framework, igual em todo erro.
                $isApp = str_contains($framePath, '/app/')
                    || str_contains($framePath, '/jonytonet/ptah/')
                    || str_contains($framePath, '/ptah/src/');

                if (! $isApp || count($frames) >= $maxFrames) {
                    $hidden++;

                    continue;
                }

                $frames[] = [
                    'n' => (int) $f[1],
                    'file' => self::relative($f[2], $basePath),
                    'line' => (int) $f[3],
                    'call' => trim($f[4]),
                ];
            }
        }

        return [
            'timestamp' => $timestamp,
            'env' => $env,
            'level' => $level,
            'exception' => $exception,
            'code' => $code,
            'message' => trim($message),
            'sql' => $sql,
            'file' => $file,
            'line' => $line,
            'frames' => $frames,
            'hidden_frames' => $hidden,
            'error_id' => $errorId,
        ];
    }

    private static function tail(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }

        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        try {
            if ($size > self::TAIL_BYTES) {
                fseek($handle, -self::TAIL_BYTES, SEEK_END);
            }

            return (string) stream_get_contents($handle);
        } finally {
            fclose($handle);
        }
    }

    private static function relative(string $path, ?string $basePath): string
    {
        $path = str_replace('\\', '/', $path);

        if ($basePath === null || $basePath === '') {
            return $path;
        }

        $base = rtrim(str_replace('\\', '/', $basePath), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
