<?php

declare(strict_types=1);

namespace Ptah\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Ptah\Models\UserPreference;

/**
 * Trait to add user preference support to the User model.
 *
 * Usage: add `use HasUserPreferences;` to the App\Models\User model
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
        return $this->hasMany(UserPreference::class);
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
