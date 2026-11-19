<?php

declare(strict_types=1);

namespace Ptah\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * What in a project's own views and scripts assumes a document load — the
 * things to look at before turning the Livewire layout's single-page
 * navigation on (`ptah.forge.spa_navigation`). The checklist of
 * docs/Configuration.md, done by reading the files.
 *
 * It reads text, not behaviour: every finding is a line to LOOK at, with the
 * reason, not a verdict. A guarded listener or a script already marked
 * `data-navigate-once` on another line is still listed.
 */
final class SpaAudit
{
    private const VIEW_SUFFIXES = ['.blade.php'];

    private const SCRIPT_SUFFIXES = ['.js', '.mjs', '.cjs', '.ts', '.jsx', '.tsx', '.vue'];

    /**
     * rule => [pattern, where ('any' | 'view'), message, action].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    private const RULES = [
        'dom-content-loaded' => [
            '/DOMContentLoaded/',
            'any',
            'runs once per document: it does not fire again when a page arrives by navigation',
            "use document.addEventListener('livewire:navigated', …) — it also fires on the first load",
        ],
        'window-onload' => [
            '/window\.onload\s*=|(?:window\.)?addEventListener\(\s*[\'"]load[\'"]/',
            'any',
            'window load fires once per document, not per page',
            "use document.addEventListener('livewire:navigated', …)",
        ],
        'wire-navigate' => [
            // Not `livewire:navigate(d)`, the event.
            '/(?<![A-Za-z])wire:navigate(?![A-Za-z])/',
            'view',
            "Livewire's own navigate link prefetches into a cache with a race (still present in 4.4.8): a click may never land",
            'use @ptahNavigate($url) in the <a>, or navigate on <x-forge-button href>',
        ],
        'start-on-load' => [
            '/startOnLoad|autoStart\s*:\s*true|auto[-_]?init/i',
            'any',
            'a library that starts itself on load does not start again on the next page',
            "start it on 'livewire:navigated', or put the page in ptah.forge.spa_navigation_except",
        ],
        'global-listener-in-view' => [
            '/\b(?:window|document)\.addEventListener\(/',
            'view',
            'a view rendered again on every page adds this listener again: it piles up',
            'register once (a flag on window), or use Alpine\'s @event.window / remove it in destroy()',
        ],
        'interval-in-view' => [
            '/\bsetInterval\(/',
            'view',
            'an interval keeps running after its page is gone',
            "clear it on 'livewire:navigating' (or in the component's destroy())",
        ],
    ];

    /**
     * @param  list<string>  $dirs  absolute directories
     * @return list<array{rule: string, file: string, line: int, code: string, message: string, action: string}>
     */
    public static function scan(array $dirs, string $base): array
    {
        $findings = [];
        $base = rtrim(str_replace('\\', '/', $base), '/').'/';

        foreach ($dirs as $dir) {
            foreach (self::files($dir) as $path => $kind) {
                $relative = str_replace('\\', '/', $path);
                $relative = str_starts_with($relative, $base) ? substr($relative, strlen($base)) : $relative;

                foreach ((array) @file($path, FILE_IGNORE_NEW_LINES) as $index => $line) {
                    if (! is_string($line) || $line === '' || strlen($line) > 2000) {
                        continue;
                    }

                    foreach (self::RULES as $rule => [$pattern, $where, $message, $action]) {
                        if ($where === 'view' && $kind !== 'view') {
                            continue;
                        }
                        // The listener for the navigation itself is the fix, not the problem.
                        if ($rule === 'global-listener-in-view' && preg_match('/livewire:navigat|alpine:navigat/', $line) === 1) {
                            continue;
                        }
                        if (preg_match($pattern, $line) !== 1) {
                            continue;
                        }

                        $findings[] = [
                            'rule' => $rule,
                            'file' => $relative,
                            'line' => $index + 1,
                            'code' => mb_substr(trim($line), 0, 120),
                            'message' => $message,
                            'action' => $action,
                        ];
                    }
                }
            }
        }

        usort($findings, fn (array $a, array $b) => [$a['rule'], $a['file'], $a['line']] <=> [$b['rule'], $b['file'], $b['line']]);

        return $findings;
    }

    /**
     * The files to read under a directory: path => 'view' | 'script'. Skips
     * dependencies, builds and ptah's own published views (the package's
     * copies are already made for the navigation).
     *
     * @return array<string, string>
     */
    private static function files(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $out = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $normal = str_replace('\\', '/', $path);

            if (preg_match('#/(node_modules|vendor/ptah|build|dist)/#', $normal) === 1) {
                continue;
            }

            foreach (self::VIEW_SUFFIXES as $suffix) {
                if (str_ends_with($normal, $suffix)) {
                    $out[$path] = 'view';

                    continue 2;
                }
            }
            foreach (self::SCRIPT_SUFFIXES as $suffix) {
                if (str_ends_with($normal, $suffix)) {
                    $out[$path] = 'script';

                    continue 2;
                }
            }
        }

        ksort($out);

        return $out;
    }

    /** @return list<string> */
    public static function rules(): array
    {
        return array_keys(self::RULES);
    }
}
