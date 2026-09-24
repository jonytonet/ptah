<?php

declare(strict_types=1);

namespace Ptah\Support;

use InvalidArgumentException;

/**
 * A module spec turned into the ordered list of artisan calls that build it.
 *
 * The spec (JSON):
 *
 *   {
 *     "module": "Catalog",                    // optional sub-folder for every entity
 *     "role": "admin",                        // optional: ptah:permission:sync --role
 *     "grant": "all",                         // optional, with role
 *     "seed": true,                           // optional: run the generated seeders
 *     "entities": {
 *       "Category": { "fields": "name:string(80),is_active:boolean:default(true)" },
 *       "Product":  {
 *         "fields": ["name:string", "price:decimal(10,2)", "category_id:unsignedBigInteger"],
 *         "columns": ["price:money:label=Preço"],   // ptah:config --column (same for
 *         "filters": ["category_id:searchdropdown"], //   filters/actions/styles/set/permissions)
 *         "api": false, "factory": true, "menu": true, "soft_deletes": true
 *       }
 *     }
 *   }
 *
 * Order matters and is the part a person gets wrong: a foreign key's target
 * has to exist first — for the migration's constraint, for the model import
 * that ptah:forge resolves only when the class is already there, and for the
 * factory that takes an existing parent row. So entities are sorted by their
 * FKs (topologically, spec order among equals), and a cycle is an error named
 * with its members instead of a half-built module.
 *
 * Nothing runs here — this only plans, so `--dry-run` prints exactly what the
 * real run executes.
 */
final class BlueprintPlan
{
    private const PASSTHROUGH = ['columns' => 'column', 'filters' => 'filter', 'actions' => 'action', 'styles' => 'style', 'set' => 'set', 'permissions' => 'permission'];

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array{label: string, command: string, args: array<string, mixed>}>
     */
    public static function steps(array $spec, bool $migrate = true): array
    {
        $entities = self::entities($spec);
        $steps = [];

        foreach (self::order($entities) as $name) {
            $e = $entities[$name];
            $args = ['entity' => $e['key'], '--fields' => $e['fields']];

            if ($e['api']) {
                $args['--api'] = true;
            }
            if ($e['factory']) {
                $args['--factory'] = true;
            }
            if (! $e['menu']) {
                $args['--no-menu'] = true;
            }
            if (! $e['soft_deletes']) {
                $args['--no-soft-deletes'] = true;
            }

            $steps[] = ['label' => "forge {$e['key']}", 'command' => 'ptah:forge', 'args' => $args];
        }

        if ($migrate) {
            $steps[] = ['label' => 'migrate', 'command' => 'migrate', 'args' => []];
        }

        foreach (self::order($entities) as $name) {
            $e = $entities[$name];
            $args = [];

            foreach (self::PASSTHROUGH as $specKey => $option) {
                if ($e['config'][$specKey] !== []) {
                    $args["--{$option}"] = $e['config'][$specKey];
                }
            }

            if ($args !== []) {
                $steps[] = ['label' => "config {$e['key']}", 'command' => 'ptah:config', 'args' => ['model' => $e['key'], '--non-interactive' => true] + $args];
            }
        }

        if (array_filter(array_column($entities, 'menu')) !== []) {
            $steps[] = ['label' => 'menu-sync', 'command' => 'ptah:menu-sync', 'args' => []];
        }

        if (! empty($spec['role'])) {
            $args = ['--role' => (string) $spec['role']];
            if (! empty($spec['grant'])) {
                $args['--grant'] = (string) $spec['grant'];
            }
            $steps[] = ['label' => "permission:sync --role={$spec['role']}", 'command' => 'ptah:permission:sync', 'args' => $args];
        }

        if (! empty($spec['seed'])) {
            foreach (self::order($entities) as $name) {
                $e = $entities[$name];
                if ($e['factory']) {
                    $class = 'Database\\Seeders\\'.str_replace('/', '\\', $e['key']).'Seeder';
                    $steps[] = ['label' => "seed {$e['key']}", 'command' => 'db:seed', 'args' => ['--class' => $class]];
                }
            }
        }

        return $steps;
    }

    /**
     * Entity names in dependency order: every FK target before its dependents.
     *
     * @param  array<string, array<string, mixed>>  $entities
     * @return list<string>
     */
    public static function order(array $entities): array
    {
        $deps = [];
        foreach ($entities as $name => $e) {
            $deps[$name] = array_values(array_filter(
                $e['depends'],
                fn (string $d) => $d !== $name && isset($entities[$d])
            ));
        }

        $sorted = [];
        $state = [];

        $visit = function (string $name, array $path) use (&$visit, &$sorted, &$state, $deps): void {
            if (($state[$name] ?? null) === 'done') {
                return;
            }

            if (($state[$name] ?? null) === 'visiting') {
                $cycle = array_slice($path, (int) array_search($name, $path, true));
                throw new InvalidArgumentException('Foreign-key cycle between entities: '.implode(' → ', [...$cycle, $name]).' — make one of the FKs nullable and add it with ptah:field after both exist.');
            }

            $state[$name] = 'visiting';
            foreach ($deps[$name] as $dep) {
                $visit($dep, [...$path, $name]);
            }
            $state[$name] = 'done';
            $sorted[] = $name;
        };

        foreach (array_keys($entities) as $name) {
            $visit($name, []);
        }

        return $sorted;
    }

    /**
     * Normalized entities, keyed by short name (the FK target name).
     *
     * @param  array<string, mixed>  $spec
     * @return array<string, array{key: string, fields: string, depends: list<string>, api: bool, factory: bool, menu: bool, soft_deletes: bool, config: array<string, list<string>>}>
     */
    public static function entities(array $spec): array
    {
        $raw = $spec['entities'] ?? null;

        if (! is_array($raw) || $raw === []) {
            throw new InvalidArgumentException('The spec needs a non-empty "entities" object: { "Product": { "fields": "name:string" } }.');
        }

        $module = trim(str_replace('\\', '/', (string) ($spec['module'] ?? '')), '/');
        $inspector = new SchemaInspector;
        $out = [];

        foreach ($raw as $name => $e) {
            if (! is_string($name) || preg_match('#^[A-Z][A-Za-z0-9]*(/[A-Z][A-Za-z0-9]*)*$#', $name) !== 1) {
                throw new InvalidArgumentException("Entity \"{$name}\": the name must be PascalCase (Product, or Catalog/Product).");
            }

            $e = is_array($e) ? $e : [];
            $fields = $e['fields'] ?? '';
            $fields = is_array($fields) ? implode(',', array_map('strval', $fields)) : (string) $fields;

            if (trim($fields) === '') {
                throw new InvalidArgumentException("Entity \"{$name}\": \"fields\" is empty.");
            }

            $short = basename(str_replace('\\', '/', $name));
            $key = str_contains($name, '/') || $module === '' ? $name : "{$module}/{$name}";

            $depends = [];
            foreach ($inspector->fromString($fields) as $field) {
                if (str_ends_with($field->name, '_id') && in_array($field->type, ['unsignedBigInteger', 'foreignId', 'bigInteger'], true)) {
                    $depends[] = $field->relatedModel();
                }
            }

            $config = [];
            foreach (array_keys(self::PASSTHROUGH) as $specKey) {
                $value = $e[$specKey] ?? [];
                $config[$specKey] = array_values(array_map('strval', is_array($value) ? $value : [$value]));
            }

            $out[$short] = [
                'key' => $key,
                'fields' => $fields,
                'depends' => array_values(array_unique([...$depends, ...array_map('strval', (array) ($e['depends'] ?? []))])),
                'api' => (bool) ($e['api'] ?? false),
                'factory' => (bool) ($e['factory'] ?? true),
                'menu' => (bool) ($e['menu'] ?? true),
                'soft_deletes' => (bool) ($e['soft_deletes'] ?? true),
                'config' => $config,
            ];
        }

        return $out;
    }
}
