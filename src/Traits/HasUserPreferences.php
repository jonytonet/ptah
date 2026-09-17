<?php

declare(strict_types=1);

namespace Ptah\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Ptah\Models\UserPreference;
use Ptah\Support\PreferenceOwners;

/**
 * Trait to add user preference support to the host's identity model.
 *
 * Usage: add `use HasUserPreferences;` to the model you authenticate — which is
 * not necessarily `App\Models\User`.
 *
 * Apply it to ONE identity. `user_preferences` keys rows by `user_id` alone and
 * its unique is `['user_id', 'key']`, so two identities with overlapping ids
 * silently share rows; see Ptah\Support\PreferenceOwners, which says so in the
 * log when it happens.
 */
trait HasUserPreferences
{
    /**
     * Clean up preferences when the user is really gone.
     *
     * The foreign key carried `ON DELETE CASCADE`, and a host whose identity
     * lives outside a constrainable table — more than one identity, another
     * connection, `PTAH_PREFERENCES_FK=false` — no longer has it. So the
     * cleanup moves here, where it works for every host alike.
     *
     * Two deliberate choices. A SOFT delete is not a deletion: the row is
     * coming back, and so should the theme the person chose. And doing it in
     * the model rather than in the engine makes it visible to the application —
     * a database cascade passes underneath Eloquent, firing no events and
     * leaving nothing for an observer or an audit trail to see.
     */
    public static function bootHasUserPreferences(): void
    {
        // O registro vive num objeto proprio, e nao numa estatica deste trait:
        // estatica declarada em TRAIT e por classe que o usa, entao um contador
        // aqui leria sempre 1 e o aviso nunca dispararia. Foi assim que a
        // primeira versao disto saiu — sem efeito nenhum.
        PreferenceOwners::register(static::class);

        static::deleted(function ($model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            UserPreference::where('user_id', $model->getKey())->delete();
        });
    }

    /**
     * Relationship with user preferences.
     */
    public function preferences(): HasMany
    {
        // A coluna e NOMEADA, nunca inferida. Sem o segundo argumento o
        // Eloquent a monta a partir do nome da CLASSE pai —
        // `Str::snake(class_basename($this)).'_'.$this->getKeyName()` — entao
        // num host cujo model de identidade e `PortalStaffUser` ele procurava
        // `user_preferences.portal_staff_user_id`, coluna que nao existe (e
        // com chave propria seria pior: `portal_staff_user_codigo`).
        //
        // Mesma familia da FK que a 1.34.6 corrigiu no esquema — algo deduzido
        // de um nome em vez de declarado — e sobreviveu aquela release porque
        // `UserPreference::user()` foi corrigido e isto nao.
        //
        // So a chave estrangeira precisa ser dita: a local key padrao do
        // hasMany ja e `$this->getKeyName()`, correta para qualquer tipo de
        // chave.
        return $this->hasMany(UserPreference::class, 'user_id');
    }

    /**
     * Sets a preference for this user.
     *
     * @param  string  $key  Preference key
     * @param  mixed  $value  Preference value
     * @param  string  $group  Preference group (default: 'general')
     */
    public function setPreference(
        string $key,
        mixed $value,
        string $group = 'general'
    ): UserPreference {
        return UserPreference::set($this->getKey(), $key, $value, $group);
    }

    /**
     * Gets a preference for this user.
     *
     * @param  string  $key  Preference key
     * @param  mixed  $default  Default value if not found
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return UserPreference::get($this->getKey(), $key, $default);
    }

    /**
     * Gets all preferences of a group for this user.
     *
     * @param  string  $group  Preference group
     * @return array<string, mixed>
     */
    public function getPreferenceGroup(string $group): array
    {
        return UserPreference::getGroup($this->getKey(), $group);
    }

    /**
     * Removes a preference for this user.
     *
     * @param  string  $key  Preference key
     */
    public function removePreference(string $key): bool
    {
        return UserPreference::remove($this->getKey(), $key);
    }
}
