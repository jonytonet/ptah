<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Prepares a host's `vite.config.js` for the React or Vue stack (1.59.0).
 *
 * Four additions, each only when missing — running it twice changes nothing:
 *  - the framework plugin (import + entry in `plugins`);
 *  - the ptah entry file in the Laravel plugin's `input`;
 *  - the `@ptah` alias to the package's sources in `vendor/`;
 *  - `dedupe`, so the package's sources and the app share ONE React/Vue.
 *
 * It edits text, not an AST: a config it does not recognise is left alone and
 * reported, with the lines to add by hand.
 */
final class ViteConfigPatcher
{
    public const ALIAS_TARGET = './vendor/jonytonet/ptah/resources/js';

    /**
     * @return array{content: string, missing: list<string>} `missing`: what could not be added
     */
    public static function patch(string $content, string $stack, string $entry): array
    {
        $missing = [];
        [$plugin, $package, $call] = $stack === 'vue'
            ? ['vue', '@vitejs/plugin-vue', 'vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } })']
            : ['react', '@vitejs/plugin-react', 'react()'];

        // 1. Imports, after the last existing import line.
        $imports = [];
        if (! str_contains($content, $package)) {
            $imports[] = "import {$plugin} from '{$package}';";
        }
        if (! preg_match('/\bfileURLToPath\b/', $content)) {
            $imports[] = "import { fileURLToPath, URL } from 'node:url';";
        }
        if ($imports !== []) {
            $block = implode("\n", $imports);
            if (preg_match_all('/^import .*;[ \t]*$/m', $content, $m, PREG_OFFSET_CAPTURE)) {
                $last = end($m[0]);
                $at = $last[1] + strlen($last[0]);
                $content = substr($content, 0, $at)."\n".$block.substr($content, $at);
            } else {
                $content = $block."\n".$content;
            }
        }

        // 2. The plugin, first in `plugins`.
        if (! preg_match('/\b'.$plugin.'\s*\(/', $content)) {
            $patched = preg_replace('/plugins\s*:\s*\[/', "plugins: [\n        {$call},", $content, 1, $count);
            if ($count === 1 && $patched !== null) {
                $content = $patched;
            } else {
                $missing[] = "plugins: [ {$call}, … ]";
            }
        }

        // 3. The entry file, in the Laravel plugin's `input`.
        // Quoted: `ptah-app.js` is also the start of `ptah-app.jsx`.
        if (! str_contains($content, "'{$entry}'") && ! str_contains($content, "\"{$entry}\"")) {
            $patched = preg_replace('/input\s*:\s*\[/', "input: ['{$entry}', ", $content, 1, $count);
            if ($count !== 1 || $patched === null) {
                // `input: 'resources/js/app.js'` — a single string.
                $patched = preg_replace('/input\s*:\s*([\'"][^\'"]+[\'"])/', "input: ['{$entry}', $1]", $content, 1, $count);
            }
            if ($count === 1 && $patched !== null) {
                $content = $patched;
            } else {
                $missing[] = "laravel({ input: ['{$entry}', …] })";
            }
        }

        // 4. The alias and the dedupe. An app that changes stack already has
        // the alias: only the dedupe list grows.
        if (str_contains($content, "'@ptah'")) {
            foreach ($stack === 'vue' ? ['vue'] : ['react', 'react-dom'] as $library) {
                if (! preg_match('/dedupe\s*:\s*\[[^\]]*[\'"]'.preg_quote($library, '/').'[\'"]/', $content)) {
                    $content = preg_replace('/dedupe\s*:\s*\[/', "dedupe: ['{$library}', ", $content, 1) ?? $content;
                }
            }
        } else {
            $alias = "'@ptah': fileURLToPath(new URL('".self::ALIAS_TARGET."', import.meta.url))";
            $dedupe = $stack === 'vue' ? "['vue']" : "['react', 'react-dom']";

            if (preg_match('/\bresolve\s*:/', $content)) {
                // A resolve block of the host's own: not ours to rewrite.
                $missing[] = "resolve: { alias: { {$alias} }, dedupe: {$dedupe} }";
            } else {
                $block = "\n    resolve: {\n        alias: {\n            {$alias},\n        },\n        dedupe: {$dedupe},\n    },";
                $patched = preg_replace('/defineConfig\s*\(\s*\{/', 'defineConfig({'.$block, $content, 1, $count);
                if ($count === 1 && $patched !== null) {
                    $content = $patched;
                } else {
                    $missing[] = "resolve: { alias: { {$alias} }, dedupe: {$dedupe} }";
                }
            }
        }

        return ['content' => $content, 'missing' => $missing];
    }
}
