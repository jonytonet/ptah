<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ptah\Support\UserIdentity;

/**
 * Point an existing `user_preferences.user_id` at the identity the host
 * configured.
 *
 * ── Why this is a command and not a migration ────────────────────────────
 *
 * The obvious vehicle for "fix an existing database" is an upgrade migration,
 * and it is the wrong one here. `PtahServiceProvider` calls
 * `loadMigrationsFrom()`, so a migration the package ships is AUTO-DISCOVERED:
 * it runs on the consumer's next `php artisan migrate`, executed for their own
 * unrelated reasons, in whatever environment that happens to be. Schema
 * arriving that way is a side effect, not a decision — which is the rule
 * `SchemaIsFrozenTest` exists to keep, and it caught this.
 *
 * It matters more than usual here, because this operation can legitimately
 * REFUSE: a database with preferences whose user no longer exists must be
 * looked at by a person. As a migration, that refusal would fail somebody's
 * deploy, for a change they never asked for. As a command, it is run when the
 * operator is ready and reading the output.
 *
 * ── What it will not do ──────────────────────────────────────────────────
 *
 * It deletes nothing. Orphan rows stop the run with a count, because the
 * package cannot know whether they belong to an identity the host abandoned
 * but still means to restore. And it leaves the column TYPE alone: moving a
 * populated `bigint` to `uuid` is a data migration, not a schema tweak, and
 * doing it unattended would be worse than the defect.
 *
 * Uso:
 *   php artisan ptah:preferences:realign --dry-run
 *   php artisan ptah:preferences:realign
 */
class PreferencesRealignCommand extends Command
{
    protected $signature = 'ptah:preferences:realign
        {--dry-run : Show what would change and exit}
        {--force : Skip the confirmation prompt (for deploy scripts)}';

    protected $description = 'Realign user_preferences.user_id to the configured identity table';

    public function handle(): int
    {
        if (! Schema::hasTable('user_preferences')) {
            $this->components->error('A tabela user_preferences não existe — rode `php artisan migrate` primeiro.');

            return self::FAILURE;
        }

        $identity = UserIdentity::resolve();
        $current = self::currentTarget();
        $wanted = $identity->shouldConstrain() ? $identity->table : null;

        $this->components->twoColumnDetail('Identidade configurada', $identity->table.'.'.$identity->keyName);
        $this->components->twoColumnDetail('FK atual', $current ?? '<nenhuma>');
        $this->components->twoColumnDetail('FK desejada', $wanted === null ? '<nenhuma>' : $wanted.'.'.$identity->keyName);

        if ($current === $wanted) {
            $this->components->info('Nada a fazer: a chave estrangeira já aponta para o lugar certo.');

            return self::SUCCESS;
        }

        if ($wanted !== null) {
            $orphans = $this->orphanCount($identity);

            $this->components->twoColumnDetail('Preferências sem usuário correspondente', (string) $orphans);

            if ($orphans > 0) {
                $this->components->error(
                    "Há {$orphans} preferência(s) cujo user_id não existe em [{$identity->table}]. ".
                    'Revise e limpe manualmente antes de realinhar — este comando não apaga dado.'
                );
                $this->line('  Para inspecionar:');
                $this->line("  select * from user_preferences p where p.user_id not in (select {$identity->keyName} from {$identity->table});");

                return self::FAILURE;
            }
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry-run: nada foi alterado.');

            return self::SUCCESS;
        }

        // Operação de esquema em tabela de produção: confirmada por uma pessoa,
        // salvo quando um script de deploy assume a decisão com --force.
        if (! $this->option('force') && ! $this->confirmToProceed($current, $wanted)) {
            $this->components->warn('Cancelado.');

            return self::FAILURE;
        }

        if ($current !== null) {
            Schema::table('user_preferences', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        }

        if ($wanted !== null) {
            Schema::table('user_preferences', function (Blueprint $table) use ($identity) {
                $table->foreign('user_id')
                    ->references($identity->keyName)
                    ->on($identity->table)
                    ->cascadeOnDelete();
            });
        }

        $this->components->info('Chave estrangeira realinhada.');

        return self::SUCCESS;
    }

    /**
     * The table `user_id` currently points at, or null when unconstrained.
     *
     * `Schema::getForeignKeys()` is native since Laravel 11 — no Doctrine.
     */
    public static function currentTarget(): ?string
    {
        foreach (Schema::getForeignKeys('user_preferences') as $key) {
            if (in_array('user_id', $key['columns'] ?? [], true)) {
                return $key['foreign_table'] ?? null;
            }
        }

        return null;
    }

    private function orphanCount(UserIdentity $identity): int
    {
        return DB::table('user_preferences')
            ->whereNotIn('user_id', DB::table($identity->table)->select($identity->keyName))
            ->count();
    }

    private function confirmToProceed(?string $current, ?string $wanted): bool
    {
        $this->newLine();
        $this->warn('  CONFIRMAÇÃO — alteração de esquema em user_preferences');
        $this->line('  operação ...... '.($current === null ? 'criar' : ($wanted === null ? 'remover' : 'substituir')).' a chave estrangeira de user_id');
        $this->line('  ambiente ...... '.app()->environment());
        $this->line('  conexão ....... '.config('database.default'));
        $this->line('  reversível .... sim (rodando de novo com a configuração anterior)');
        $this->line('  dados ......... nenhuma linha é apagada');
        $this->newLine();

        return $this->confirm('Prosseguir?', false);
    }
}
