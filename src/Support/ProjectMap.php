<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Ptah\Models\CrudConfig;
use Ptah\Models\Menu;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * The project as an agent needs it at the start of a session, in one read.
 *
 * Without it, "what is there?" is answered file by file: list app/Models, open
 * each model, open its migration, query crud_configs, read the menu seeder,
 * grep for TODOs — easily tens of thousands of tokens before the first useful
 * edit. This reads the same truth the runtime reads (the models' reflection,
 * the live schema, the crud_configs and menus tables) and prints one line per
 * entity.
 *
 * Only what can be read without side effects: a relation is listed when its
 * method DECLARES a Relation return type — calling an undeclared method to
 * find out what it returns is exactly the guess this refuses to make.
 */
final class ProjectMap
{
    /**
     * Columns every generated entity has; summarized, not listed.
     */
    private const MANAGED = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by'];

    private const MAX_TODOS = 30;

    /**
     * Eloquent model classes declared under a directory, read from each file's
     * own namespace declaration (not its path, which a custom autoload breaks).
     *
     * @return list<class-string<Model>>
     */
    public static function discoverModels(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $classes = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());

            if (preg_match('/^namespace\s+([^;]+);/m', $code, $ns) !== 1
                || preg_match('/^(?:final\s+|abstract\s+)*class\s+(\w+)/m', $code, $cls) !== 1) {
                continue;
            }

            $class = trim($ns[1]).'\\'.$cls[1];

            if (! class_exists($class)) {
                require_once $file->getPathname();
            }

            if (class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @param  list<class-string<Model>>  $models
     * @return array{entities: list<array<string, mixed>>, screens: list<array<string, mixed>>, menu: list<string>, todos: list<string>}
     */
    public static function build(array $models, ?string $scanDir = null, ?string $basePath = null): array
    {
        return [
            'entities' => array_map(self::entity(...), $models),
            'screens' => self::screens(),
            'menu' => self::menu(),
            'todos' => $scanDir !== null ? self::todos($scanDir, $basePath) : [],
        ];
    }

    /**
     * @param  class-string<Model>  $class
     * @return array<string, mixed>
     */
    private static function entity(string $class): array
    {
        /** @var Model $model */
        $model = new $class;
        $table = $model->getTable();
        $relations = self::relations($model);

        $fkTargets = [];
        foreach ($relations as $r) {
            if ($r['type'] === 'belongsTo' && $r['foreign_key'] !== null) {
                $fkTargets[$r['foreign_key']] = $r['related'];
            }
        }

        $fields = [];
        $managed = [];
        $exists = false;

        try {
            $exists = Schema::connection($model->getConnectionName())->hasTable($table);

            foreach ($exists ? Schema::connection($model->getConnectionName())->getColumns($table) : [] as $column) {
                $name = (string) $column['name'];

                if (in_array($name, self::MANAGED, true)) {
                    $managed[] = $name;

                    continue;
                }

                $type = strtolower($column['type_name']);
                $fields[] = $name.':'.$type.($column['nullable'] ? '?' : '')
                    .(isset($fkTargets[$name]) ? '→'.$fkTargets[$name] : '');
            }
        } catch (\Throwable) {
            // Sem conexao: o mapa ainda sai, com a tabela marcada como ausente.
        }

        return [
            'class' => $class,
            'table' => $table,
            'table_exists' => $exists,
            'soft_deletes' => in_array(SoftDeletes::class, class_uses_recursive($class), true),
            'fields' => $fields,
            'audit' => array_values(array_diff($managed, ['id'])) !== [],
            'relations' => array_map(fn ($r) => $r['name'].'→'.$r['related'].' ('.$r['type'].')', $relations),
        ];
    }

    /**
     * @return list<array{name: string, type: string, related: string, foreign_key: string|null}>
     */
    private static function relations(Model $model): array
    {
        $out = [];

        foreach ((new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // So as relacoes da propria app: nada herdado do Eloquent ou de traits do pacote.
            if ($method->getDeclaringClass()->getName() !== $model::class || $method->getNumberOfRequiredParameters() > 0 || $method->isStatic()) {
                continue;
            }

            $type = $method->getReturnType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin() || ! is_a($type->getName(), Relation::class, true)) {
                continue;
            }

            try {
                $relation = $model->{$method->getName()}();
            } catch (\Throwable) {
                continue;
            }

            if (! $relation instanceof Relation) {
                continue;
            }

            $out[] = [
                'name' => $method->getName(),
                'type' => lcfirst(class_basename($relation)),
                'related' => class_basename($relation->getRelated()),
                'foreign_key' => $relation instanceof BelongsTo ? $relation->getForeignKeyName() : null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function screens(): array
    {
        try {
            if (! Schema::hasTable('crud_configs')) {
                return [];
            }

            return CrudConfig::query()->orderBy('model')->orderBy('route')->get()->map(function (CrudConfig $c): array {
                $config = $c->config;
                $cols = is_array($config['cols'] ?? null) ? $config['cols'] : [];

                return [
                    'model' => (string) $c->model,
                    'route' => (string) ($c->route ?? ''),
                    'permission' => $config['permissions']['permissionIdentifier'] ?? null,
                    'cols' => count($cols),
                    'filters' => count(is_array($config['customFilters'] ?? null) ? $config['customFilters'] : []),
                ];
            })->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * The database menu as indented "Group > Link (url)" lines.
     *
     * @return list<string>
     */
    private static function menu(): array
    {
        try {
            if (! Schema::hasTable('menus')) {
                return [];
            }

            $rows = Menu::query()->where('is_active', true)->orderBy('link_order')->get(['id', 'parent_id', 'text', 'url']);
        } catch (\Throwable) {
            return [];
        }

        $byParent = $rows->groupBy(fn ($m) => (int) ($m->parent_id ?? 0));
        $out = [];

        $walk = function (int $parent, string $prefix) use (&$walk, $byParent, &$out): void {
            foreach ($byParent->get($parent, collect()) as $item) {
                $label = $prefix.$item->text;
                $children = $byParent->get((int) $item->id);

                if ($children && $children->isNotEmpty()) {
                    $walk((int) $item->id, $label.' > ');

                    continue;
                }

                $out[] = $label.($item->url ? " ({$item->url})" : '');
            }
        };

        $walk(0, '');

        return $out;
    }

    /**
     * `TODO:` lines left in the application — the generators leave them where
     * a human decision is needed, so they are the open work of the project.
     *
     * @return list<string>
     */
    private static function todos(string $dir, ?string $basePath): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $base = $basePath !== null ? rtrim(str_replace('\\', '/', $basePath), '/').'/' : '';
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            foreach (file($file->getPathname()) ?: [] as $i => $line) {
                if (preg_match('/(?:\/\/|#|\*)\s*TODO:?\s*(.+)$/', $line, $m) !== 1) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                $out[] = ($base !== '' && str_starts_with($path, $base) ? substr($path, strlen($base)) : $path).':'.($i + 1).'  '.trim($m[1]);

                if (count($out) >= self::MAX_TODOS) {
                    $out[] = '… (more — first '.self::MAX_TODOS.' shown)';

                    return $out;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array{entities: list<array<string, mixed>>, screens: list<array<string, mixed>>, menu: list<string>, todos: list<string>}  $map
     */
    public static function toText(array $map, string $modelsNamespace = 'App\\Models\\'): string
    {
        $short = fn (string $class): string => str_starts_with($class, $modelsNamespace)
            ? str_replace('\\', '/', substr($class, strlen($modelsNamespace)))
            : $class;

        $lines = ['# Project map (ptah:map)', ''];

        $lines[] = '## Entities ('.count($map['entities']).')';
        foreach ($map['entities'] as $e) {
            $flags = array_filter([
                $e['table_exists'] ? null : 'NO TABLE',
                $e['soft_deletes'] ? 'soft' : null,
                $e['audit'] ? 'audit' : null,
            ]);

            $lines[] = $short($e['class']).'  '.$e['table'].($flags ? '  ['.implode(',', $flags).']' : '');
            if ($e['fields'] !== []) {
                $lines[] = '  '.implode(' ', $e['fields']);
            }
            if ($e['relations'] !== []) {
                $lines[] = '  rel: '.implode(', ', $e['relations']);
            }
        }

        $lines[] = '';
        $lines[] = '## Screens ('.count($map['screens']).')';
        foreach ($map['screens'] as $s) {
            // A chave do forge ('Catalog/Product') ja e curta; um FQCN e encurtado.
            $lines[] = (str_contains($s['model'], '\\') ? $short($s['model']) : $s['model'])
                .'  route='.($s['route'] !== '' ? $s['route'] : '(global)')
                .'  perm='.($s['permission'] ?: '(none)')
                .'  cols='.$s['cols'].'  filters='.$s['filters'];
        }

        if ($map['menu'] !== []) {
            $lines[] = '';
            $lines[] = '## Menu';
            foreach ($map['menu'] as $m) {
                $lines[] = $m;
            }
        }

        if ($map['todos'] !== []) {
            $lines[] = '';
            $lines[] = '## Pending (TODO)';
            foreach ($map['todos'] as $t) {
                $lines[] = $t;
            }
        }

        return implode("\n", $lines)."\n";
    }
}
