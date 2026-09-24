<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Ptah\Support\LogErrorReader;

/**
 * `ptah:last-error` — the last error in the log, in about a hundred tokens.
 *
 * Instead of `tail -300 laravel.log`: the exception, the message, the SQL when
 * there is one, where it was thrown, and only the application's frames — the
 * vendor pipeline, identical in every error, is counted, not printed.
 *
 * Uso:
 *   php artisan ptah:last-error
 *   php artisan ptah:last-error --frames=10
 *   php artisan ptah:last-error --json
 *   php artisan ptah:last-error --file=storage/logs/laravel-2026-09-23.log
 */
class LastErrorCommand extends Command
{
    protected $signature = 'ptah:last-error
        {--file= : Log file to read (default: the most recent *.log in storage/logs)}
        {--frames=6 : How many application frames to show}
        {--json : Machine-readable output}';

    protected $description = 'Show the last error in the Laravel log, compact: exception, SQL, and only the application frames';

    public function handle(): int
    {
        $file = $this->option('file') ?: LogErrorReader::latestLogFile(storage_path('logs'));

        if (! $file || ! is_file($file)) {
            $this->components->info('No log file found in storage/logs.');

            return self::SUCCESS;
        }

        $error = LogErrorReader::lastError($file, max(0, (int) $this->option('frames')), base_path());

        if ($error === null) {
            $this->components->info('No ERROR-level entry in the tail of '.basename($file).'.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $head = "[{$error['timestamp']}] {$error['level']}";
        if ($error['exception']) {
            $head .= '  '.$error['exception'].($error['code'] !== null && $error['code'] !== '0' ? " ({$error['code']})" : '');
        }

        $this->line($head);
        $this->line($error['message']);

        if ($error['sql']) {
            $this->line('SQL: '.$error['sql']);
        }

        if ($error['file']) {
            $this->line("at {$error['file']}:{$error['line']}");
        }

        if ($error['frames'] !== []) {
            $this->line('app frames ('.count($error['frames']).' shown, '.$error['hidden_frames'].' vendor hidden):');

            foreach ($error['frames'] as $f) {
                $this->line("  #{$f['n']} {$f['file']}:{$f['line']}  {$f['call']}");
            }
        } elseif ($error['hidden_frames'] > 0) {
            $this->line("({$error['hidden_frames']} frames, none in app/ — the error is inside a dependency)");
        }

        if ($error['error_id']) {
            $this->line("ptahErrorId: {$error['error_id']}");
        }

        return self::SUCCESS;
    }
}
