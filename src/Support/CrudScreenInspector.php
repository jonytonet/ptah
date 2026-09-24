<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Ptah\Models\RecordHistory;
use Ptah\Traits\RecordsHistory;

/**
 * What a BaseCrud screen's config claims, checked against the model and the table.
 *
 * A screen whose config names a column the table does not have renders fine —
 * the cell is simply empty — and a form field outside `$fillable` saves fine,
 * minus that field. Neither throws, so neither shows up anywhere until a user
 * notices. This is where an agent would otherwise open the model, the
 * migration and the config and compare them by hand; here it is one pass over
 * the same data the runtime reads.
 *
 * Findings only — nothing is written. Each finding is a `level` (error: the
 * screen fails; warning: it runs but does not do what the config says) and a
 * one-line message meant to be acted on as it stands.
 */
final class CrudScreenInspector
{
    /**
     * Columns the framework fills; never expected in a form.
     */
    private const MANAGED = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by', 'remember_token'];

    /**
     * The model class a config's `crud`/`model` key names, resolved the way
     * BaseCrud resolves it (HasCrudQuery::resolveEloquentModel).
     */
    public static function resolveModelClass(string $name): ?string
    {
        $class = str_replace('/', '\\', $name);

        foreach ([$class, 'App\\Models\\'.$class, app()->getNamespace().'Models\\'.$class] as $candidate) {
            if (class_exists($candidate) && is_subclass_of($candidate, Model::class)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<array{level: 'error'|'warning', message: string}>
     */
    public static function inspect(Model $model, array $config): array
    {
        $findings = [];
        $table = $model->getTable();
        $connection = $model->getConnection()->getName();

        if (! Schema::connection($connection)->hasTable($table)) {
            return [['level' => 'error', 'message' => "table \"{$table}\" does not exist — run the migration"]];
        }

        /** @var array<string, array<string, mixed>> $columns */
        $columns = [];
        foreach (Schema::connection($connection)->getColumns($table) as $column) {
            $columns[(string) $column['name']] = $column;
        }

        // O trait nao quebra o save sem a tabela (historico e auxiliar) — por
        // isso o aviso precisa vir de algum lugar.
        if (in_array(RecordsHistory::class, class_uses_recursive($model), true) && ! RecordHistory::tableExists()) {
            $findings[] = ['level' => 'warning', 'message' => class_basename($model).' uses RecordsHistory but table ptah_record_history does not exist — nothing is recorded; run ptah:history:install and migrate'];
        }

        $cols = is_array($config['cols'] ?? null) ? $config['cols'] : [];
        $formFields = [];

        foreach ($cols as $col) {
            if (! is_array($col)) {
                continue;
            }

            $field = (string) ($col['colsNomeFisico'] ?? '');
            $type = (string) ($col['colsTipo'] ?? 'text');

            if ($field === '' || $type === 'action') {
                continue;
            }

            foreach (['colsRelacao' => 'relation', 'colsRelacaoNested' => 'nested relation'] as $key => $what) {
                $path = (string) ($col[$key] ?? '');
                if ($key === 'colsRelacaoNested') {
                    $path = implode('.', array_slice(explode('.', $path), 0, -1));
                }

                if ($path !== '' && ! RelationPath::isValid($model, $path)) {
                    $findings[] = ['level' => 'error', 'message' => "col \"{$field}\": {$what} \"{$path}\" is not a relationship on ".class_basename($model)];
                }
            }

            $computed = ! empty($col['colsMetodoCustom']) || str_contains($field, '.');

            if (! $computed && ! isset($columns[$field]) && ! self::isVirtualAttribute($model, $field)) {
                $findings[] = ['level' => 'warning', 'message' => "col \"{$field}\" is not a column of {$table} nor an accessor — the cell renders empty"];
            }

            foreach (['colsOrderBy' => 'sort', 'colsSource' => 'filter source'] as $key => $what) {
                $ref = (string) ($col[$key] ?? '');
                if ($ref !== '' && ! str_contains($ref, '.') && ! isset($columns[$ref])) {
                    $findings[] = ['level' => 'error', 'message' => "col \"{$field}\": {$what} column \"{$ref}\" is not in {$table} — using it throws"];
                }
            }

            $savable = self::flag($col['colsGravar'] ?? false) && self::flag($col['colsEditableForm'] ?? true);

            if ($savable && ! in_array($field, self::MANAGED, true)) {
                $formFields[] = $field;

                if (! $model->isFillable($field)) {
                    $findings[] = ['level' => 'warning', 'message' => "form field \"{$field}\" is not fillable on ".class_basename($model).' — it is silently not saved'];
                }
            }
        }

        foreach (is_array($config['customFilters'] ?? null) ? $config['customFilters'] : [] as $filter) {
            if (! is_array($filter) || empty($filter['field'])) {
                continue;
            }

            $field = (string) $filter['field'];
            $relation = (string) ($filter['field_relation'] ?? $filter['colRelation'] ?? '');

            if ($relation !== '') {
                if (! RelationPath::isValid($model, $relation)) {
                    $findings[] = ['level' => 'error', 'message' => "filter \"{$field}\": relation \"{$relation}\" is not a relationship on ".class_basename($model)];
                }

                continue;
            }

            if (! str_contains($field, '.') && ! isset($columns[$field])) {
                $findings[] = ['level' => 'error', 'message' => "filter \"{$field}\" is not a column of {$table} — filtering by it throws"];
            }
        }

        // Uma coluna NOT NULL sem default que o formulario nao preenche faz todo
        // "Novo" falhar no banco — o erro so aparece quando alguem tenta salvar.
        if ($formFields !== []) {
            foreach ($columns as $name => $column) {
                if (in_array($name, self::MANAGED, true) || in_array($name, $formFields, true)) {
                    continue;
                }

                if (($column['nullable'] ?? true) || ($column['default'] ?? null) !== null || ($column['auto_increment'] ?? false)) {
                    continue;
                }

                $findings[] = ['level' => 'warning', 'message' => "column \"{$name}\" is NOT NULL without a default and not in the form — create fails unless a hook fills it"];
            }
        }

        return $findings;
    }

    /**
     * Create → update → delete one record through the model, inside a
     * transaction that is ALWAYS rolled back. It answers what the static pass
     * cannot: whether the database accepts what the form sends — NOT NULL, FK,
     * enum, length. Model events are muted, so observers do not send the mail
     * or dispatch the job a real save would.
     *
     * @param  array<string, mixed>  $config
     * @return list<array{level: 'error'|'warning', message: string}>
     */
    public static function roundTrip(Model $model, array $config): array
    {
        $connection = $model->getConnection();
        $columns = [];
        foreach (Schema::connection($connection->getName())->getColumns($model->getTable()) as $column) {
            $columns[(string) $column['name']] = $column;
        }

        $data = [];
        $findings = [];

        foreach (is_array($config['cols'] ?? null) ? $config['cols'] : [] as $col) {
            $field = (string) ($col['colsNomeFisico'] ?? '');

            if ($field === '' || in_array($field, self::MANAGED, true) || ! isset($columns[$field])
                || ! self::flag($col['colsGravar'] ?? false) || ! self::flag($col['colsEditableForm'] ?? true)) {
                continue;
            }

            $value = self::sampleValue($model, $field, $columns[$field], $missing);

            if ($missing !== null) {
                $findings[] = ['level' => 'warning', 'message' => "write skipped: \"{$field}\" needs a {$missing} row to reference and there is none"];

                return $findings;
            }

            $data[$field] = $value;
        }

        if ($data === []) {
            return [['level' => 'warning', 'message' => 'write skipped: the form has no savable field that exists in the table']];
        }

        $connection->beginTransaction();

        try {
            $stage = 'create';
            $record = Model::withoutEvents(fn () => $model->newQuery()->create($data));

            $stage = 'update';
            // Os mesmos valores nao sujam o model e o save() nao emitiria SQL.
            $record->fill($data);
            Model::withoutEvents(fn () => $record->usesTimestamps() ? $record->touch() : $record->save());

            $stage = 'delete';
            Model::withoutEvents(fn () => $record->delete());
        } catch (\Throwable $e) {
            $findings[] = ['level' => 'error', 'message' => "write failed on {$stage}: ".self::oneLine($e->getMessage())];
        } finally {
            $connection->rollBack();
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private static function sampleValue(Model $model, string $field, array $column, ?string &$missing): mixed
    {
        $missing = null;
        $type = strtolower((string) ($column['type_name'] ?? ''));
        $full = strtolower((string) ($column['type'] ?? $type));

        if (str_ends_with($field, '_id')) {
            $method = Str::camel(substr($field, 0, -3));

            if (RelationPath::isValid($model, $method) && $model->{$method}() instanceof BelongsTo) {
                $related = $model->{$method}()->getRelated();
                $id = $related->newQuery()->value($related->getKeyName());

                if ($id === null && ! ($column['nullable'] ?? false)) {
                    $missing = class_basename($related);
                }

                return $id;
            }
        }

        if (preg_match("/^enum\\('([^']*)'/", $full, $m) === 1) {
            return $m[1];
        }

        return match (true) {
            str_contains($full, 'tinyint(1)'), $type === 'boolean', $type === 'bool' => true,
            in_array($type, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'int4', 'int8', 'int2'], true) => 1,
            in_array($type, ['decimal', 'numeric', 'float', 'double', 'real', 'float8', 'float4'], true) => 1.5,
            $type === 'date' => now()->toDateString(),
            in_array($type, ['datetime', 'timestamp', 'timestamptz'], true) => now()->toDateTimeString(),
            $type === 'time' => '12:00:00',
            in_array($type, ['json', 'jsonb'], true) => '[]',
            $type === 'uuid' => (string) Str::uuid(),
            default => 'ptck'.substr(md5(uniqid('', true)), 0, 6),
        };
    }

    private static function oneLine(string $message): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $message));

        return mb_strlen($message) > 300 ? mb_substr($message, 0, 300).'…' : $message;
    }

    private static function isVirtualAttribute(Model $model, string $field): bool
    {
        if ($model->hasGetMutator($field) || $model->hasAttributeMutator($field)) {
            return true;
        }

        return in_array($field, $model->getAppends(), true);
    }

    private static function flag(mixed $value): bool
    {
        return $value === true || $value === 'S' || $value === 1 || $value === '1';
    }
}
