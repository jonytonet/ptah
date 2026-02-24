<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Model para armazenamento de preferências de usuário em banco de dados.
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
     * Relacionamento com o model User.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class));
    }

    /**
     * Define uma preferência para o usuário.
     *
     * @param int|string $userId  ID do usuário
     * @param string     $key     Chave da preferência
     * @param mixed      $value   Valor da preferência
     * @param string     $group   Grupo da preferência (padrão: 'general')
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
     * Obtém uma preferência do usuário.
     *
     * @param int|string $userId  ID do usuário
     * @param string     $key     Chave da preferência
     * @param mixed      $default Valor padrão se não encontrado
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
     * Obtém todas as preferências de um grupo para o usuário.
     *
     * @param int|string $userId ID do usuário
     * @param string     $group  Grupo das preferências
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
     * Remove uma preferência do usuário.
     *
     * @param int|string $userId ID do usuário
     * @param string     $key    Chave da preferência
     */
    public static function remove(int|string $userId, string $key): bool
    {
        return (bool) static::where('user_id', $userId)
            ->where('key', $key)
            ->delete();
    }
}
