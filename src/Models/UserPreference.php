<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Model for storing user preferences in database.
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $key
 * @property mixed  $value
 * @property string $group
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserPreference extends Model
{
    /**
     * @var string
     */
    protected $table = 'user_preferences';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'key',
        'value',
        'group',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'json',
    ];

    /**
     * Relationship with the User model.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class));
    }

    /**
     * Sets a preference for the user.
     *
     * @param int|string $userId  User ID
     * @param string     $key     Preference key
     * @param mixed      $value   Preference value
     * @param string     $group   Preference group (default: 'general')
     */
    public static function set(
        int|string $userId,
        string $key,
        mixed $value,
        string $group = 'general'
    ): static {
        /** @var static $preference */
        $preference = static::updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value, 'group' => $group]
        );

        return $preference;
    }

    /**
     * Gets a preference for the user.
     *
     * @param int|string $userId  User ID
     * @param string     $key     Preference key
     * @param mixed      $default Default value if not found
     */
    public static function get(
        int|string $userId,
        string $key,
        mixed $default = null
    ): mixed {
        $preference = static::where('user_id', $userId)
            ->where('key', $key)
            ->first();

        return $preference?->value ?? $default;
    }

    /**
     * Gets all preferences of a group for the user.
     *
     * @param int|string $userId User ID
     * @param string     $group  Preference group
     * @return array<string, mixed>
     */
    public static function getGroup(int|string $userId, string $group): array
    {
        return static::where('user_id', $userId)
            ->where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * Removes a preference for the user.
     *
     * @param int|string $userId User ID
     * @param string     $key    Preference key
     */
    public static function remove(int|string $userId, string $key): bool
    {
        return (bool) static::where('user_id', $userId)
            ->where('key', $key)
            ->delete();
    }
}
