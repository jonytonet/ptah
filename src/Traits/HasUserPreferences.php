<?php

declare(strict_types=1);

namespace Ptah\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Ptah\Models\UserPreference;

/**
 * Trait para adicionar suporte a preferências de usuário no model User.
 *
 * Uso: adicione `use HasUserPreferences;` no model App\Models\User
 */
trait HasUserPreferences
{
    /**
     * Relacionamento com as preferências do usuário.
     */
    public function preferences(): HasMany
    {
        return $this->hasMany(UserPreference::class);
    }

    /**
     * Define uma preferência para este usuário.
     *
     * @param string $key   Chave da preferência
     * @param mixed  $value Valor da preferência
     * @param string $group Grupo da preferência (padrão: 'general')
     */
    public function setPreference(
        string $key,
        mixed $value,
        string $group = 'general'
    ): UserPreference {
        return UserPreference::set($this->getKey(), $key, $value, $group);
    }

    /**
     * Obtém uma preferência deste usuário.
     *
     * @param string $key     Chave da preferência
     * @param mixed  $default Valor padrão se não encontrado
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        return UserPreference::get($this->getKey(), $key, $default);
    }

    /**
     * Obtém todas as preferências de um grupo para este usuário.
     *
     * @param string $group Grupo das preferências
     * @return array<string, mixed>
     */
    public function getPreferenceGroup(string $group): array
    {
        return UserPreference::getGroup($this->getKey(), $group);
    }

    /**
     * Remove uma preferência deste usuário.
     *
     * @param string $key Chave da preferência
     */
    public function removePreference(string $key): bool
    {
        return UserPreference::remove($this->getKey(), $key);
    }
}
