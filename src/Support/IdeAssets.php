<?php

declare(strict_types=1);

namespace Ptah\Support;

use Ptah\Services\Validation\JsonSchemaBuilder;

/**
 * The files the ptah VS Code extension reads from vendor/jonytonet/ptah (1.52.0).
 *
 * The extension knows nothing about ptah on its own: completion, hover and
 * validation come from these files, shipped with the INSTALLED version, so the
 * editor can never offer a key or a prop the runtime does not have. They are
 * generated from the code (the schema builder, the components' own @props)
 * and committed; IdeAssetsTest fails when a committed file drifts from what
 * the code generates — `PTAH_REGEN_IDE=1` rewrites them.
 */
final class IdeAssets
{
    /**
     * Relative path (from the package root) => file contents.
     *
     * @return array<string, string>
     */
    public static function files(): array
    {
        $builder = new JsonSchemaBuilder;

        return [
            'resources/ide/crud-config.schema.json' => self::json($builder->buildCrudConfigSchema()),
            'resources/ide/crud-config-export.schema.json' => self::json($builder->buildExportFileSchema()),
            'resources/ide/components.json' => self::json(self::components()),
        ];
    }

    /**
     * Every <x-forge-*> component: its props (name and default, as written in
     * the component's props directive) and the doc comment at the top of the file.
     *
     * @return array<string, mixed>
     */
    public static function components(): array
    {
        $components = [];

        foreach (glob(self::root().'/resources/views/components/forge-*.blade.php') ?: [] as $path) {
            $source = (string) file_get_contents($path);
            $name = basename($path, '.blade.php');

            $components[$name] = [
                'tag' => 'x-'.$name,
                'doc' => self::docComment($source),
                'props' => self::props($source),
            ];
        }

        ksort($components);

        return ['version' => 1, 'components' => $components];
    }

    /**
     * @return list<array{name: string, default: string}>
     */
    private static function props(string $source): array
    {
        if (! preg_match('/@props\(\[(.*?)\]\)\s*$/ms', $source, $m)) {
            return [];
        }

        $props = [];
        foreach (preg_split('/\R/', $m[1]) ?: [] as $line) {
            if (preg_match("/^\s*'([A-Za-z_][A-Za-z0-9_]*)'\s*=>\s*(.+?),?\s*(?:\/\/.*)?$/", $line, $p)) {
                $props[] = ['name' => $p[1], 'default' => rtrim(trim($p[2]), ',')];
            }
        }

        return $props;
    }

    private static function docComment(string $source): string
    {
        if (! preg_match('/^\s*\{\{--(.*?)--\}\}/s', $source, $m)) {
            return '';
        }

        $lines = array_map('rtrim', preg_split('/\R/', trim($m[1], "\r\n")) ?: []);
        $indent = PHP_INT_MAX;
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $indent = min($indent, strlen($line) - strlen(ltrim($line)));
            }
        }

        return trim(implode("\n", array_map(fn (string $l) => substr($l, $indent === PHP_INT_MAX ? 0 : $indent), $lines)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
