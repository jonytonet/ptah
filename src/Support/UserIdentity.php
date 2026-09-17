<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Where the host keeps its users, answered from configuration instead of
 * assumed.
 *
 * `user_preferences` was the one table in the package whose foreign key did not
 * name its target: `foreignId('user_id')->constrained()`, which Laravel infers
 * as `users`. Every other `user_id` column in the package — `ptah_user_roles`,
 * `ptah_permission_audits`, `ptah_exports`, `ptah_ai_conversations` — is a plain
 * `unsignedBigInteger` with an index and no constraint, and every INTERNAL
 * foreign key names its table explicitly (`constrained('ptah_roles')`). The bare
 * `constrained()` appeared exactly once in the whole package.
 *
 * That single inference contradicted a promise the config file makes out loud,
 * next to `user_model`: "host application's User model (no hard-coded FK)". A
 * host that points `PTAH_USER_MODEL` at its own identity model could not save a
 * theme: the insert failed with a foreign key violation against a `users` table
 * it does not use.
 *
 * ── What this resolves, and in what order ────────────────────────────────
 *
 * The model comes from `ptah.permissions.user_model`, then
 * `auth.providers.users.model`, then the historical default. Both keys are
 * already in use across the package — the permission commands read the first,
 * the auth pages and `UserPreference::user()` read the second — so honouring
 * both is what keeps this from becoming a third convention.
 *
 * The key name comes from the MODEL, except when the host named one explicitly
 * in `ptah.permissions.user_id_field`. That order matters: the model is the
 * more reliable truth (it is what Eloquent actually uses), but a host that set
 * the field deliberately is telling the package what `ResolvesUser` will write
 * into `user_id`, and the constraint has to point at the same column.
 */
final class UserIdentity
{
    /**
     * @param  'int'|'uuid'|'ulid'|'string'  $keyKind  the column type `user_id` needs
     */
    public function __construct(
        public readonly string $table,
        public readonly string $keyName,
        public readonly string $keyKind,
        public readonly ?string $connection = null,
    ) {}

    public static function resolve(): self
    {
        $model = config('ptah.permissions.user_model')
            ?: config('auth.providers.users.model')
            ?: 'App\\Models\\User';

        // An installation whose model does not exist yet — a fresh scaffold, or
        // a package test — falls back to the historical default rather than
        // failing the migration.
        if (! is_string($model) || ! class_exists($model)) {
            return new self('users', 'id', 'int');
        }

        $instance = new $model;

        if (! $instance instanceof Model) {
            return new self('users', 'id', 'int');
        }

        return new self(
            $instance->getTable(),
            self::keyNameFor($instance),
            self::keyKindFor($instance),
            $instance->getConnectionName(),
        );
    }

    /**
     * Should the migration create the constraint at all?
     *
     * `auto` (the default) creates it when the resolved table is there and
     * lives on the same connection — which covers both the ordinary host and
     * the one whose identity is a model of its own. `true` demands it and fails
     * loudly. `false` never creates it, which is the honest answer for a host
     * where MORE THAN ONE identity writes preferences: no single table is a
     * valid target, and a constraint would lock the column to whichever one
     * happened to migrate first.
     */
    public function shouldConstrain(): bool
    {
        $mode = config('ptah.preferences.foreign_key', 'auto');

        if ($mode === false || $mode === 'false' || $mode === 0 || $mode === '0') {
            return false;
        }

        $reachable = $this->isOnDefaultConnection() && Schema::hasTable($this->table);

        if ($mode === true || $mode === 'true' || $mode === 1 || $mode === '1') {
            if (! $reachable) {
                throw new RuntimeException(
                    'ptah.preferences.foreign_key exige a chave estrangeira, mas a tabela de '.
                    "identidade [{$this->table}] nao esta acessivel nesta conexao."
                );
            }

            return true;
        }

        return $reachable;
    }

    /**
     * A foreign key cannot cross connections, and most engines cannot cross
     * schemas either — so an identity that lives elsewhere gets an index.
     */
    public function isOnDefaultConnection(): bool
    {
        return $this->connection === null
            || $this->connection === config('database.default');
    }

    private static function keyNameFor(Model $instance): string
    {
        $configured = config('ptah.permissions.user_id_field', 'id');

        // Only an explicit, non-default value overrides the model: the default
        // is 'id' for everybody, so treating it as explicit would silently
        // point the constraint at a column a UUID model does not have.
        if (is_string($configured) && $configured !== '' && $configured !== 'id') {
            return $configured;
        }

        return $instance->getKeyName();
    }

    /**
     * @return 'int'|'uuid'|'ulid'|'string'
     */
    private static function keyKindFor(Model $instance): string
    {
        $uses = class_uses_recursive($instance);

        if (in_array(HasUuids::class, $uses, true)) {
            return 'uuid';
        }

        if (in_array(HasUlids::class, $uses, true)) {
            return 'ulid';
        }

        // A string key without either trait is somebody's own scheme — a code,
        // a slug, an external id. `unsignedBigInteger` would be the wrong
        // column outright, which is what a bare `default =>` arm would have
        // produced.
        return $instance->getKeyType() === 'string' ? 'string' : 'int';
    }
}
