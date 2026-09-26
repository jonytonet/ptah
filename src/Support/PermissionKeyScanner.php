<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Every permission key the application's code asks about, with where.
 *
 * `@ptahCan('sales.orders', 'create')`, `ptah_can('health.records', 'read')`
 * and the `ptah.can:financial.payables,update` middleware all DENY a key that
 * is not a registered page object — to every user but master, and whoever
 * tests is usually master. The PetPlace had 47 of them across 13 screens
 * before anyone noticed. `ptah:check` compares this list with the registered
 * objects.
 *
 * Literal keys only: a key built at runtime (`ptah_can($key, …)`) cannot be
 * read from the source and is not reported.
 */
final class PermissionKeyScanner
{
    private const PATTERNS = [
        '/@ptahCan\(\s*[\'"]([^\'"]+)[\'"]/',
        '/\bptah_can\(\s*[\'"]([^\'"]+)[\'"]/',
        '/[\'"]ptah\.can:([^,\'"]+)/',
    ];

    /**
     * @param  list<string>  $dirs
     * @return list<array{key: string, file: string, line: int}>
     */
    public static function scan(array $dirs, string $basePath = ''): array
    {
        $base = rtrim(str_replace('\\', '/', $basePath), '/').'/';
        $out = [];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

            foreach ($it as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                $rel = $base !== '/' && str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;

                foreach (explode("\n", self::withoutComments((string) file_get_contents($path))) as $i => $line) {
                    foreach (self::PATTERNS as $pattern) {
                        if (preg_match_all($pattern, $line, $m) > 0) {
                            foreach ($m[1] as $key) {
                                $out[] = ['key' => trim($key), 'file' => $rel, 'line' => $i + 1];
                            }
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Blade `{{-- --}}` and PHP comments blanked out, keeping the line count —
     * a key in an explanatory comment is not a key the code asks about.
     */
    private static function withoutComments(string $source): string
    {
        $keepLines = fn (array $m) => str_repeat("\n", substr_count($m[0], "\n"));

        $source = (string) preg_replace_callback('/\{\{--.*?--\}\}/s', $keepLines, $source);
        $source = (string) preg_replace_callback('#/\*.*?\*/#s', $keepLines, $source);

        return (string) preg_replace('#(^|\s)//[^\n]*#', '$1', $source);
    }
}
