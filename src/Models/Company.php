<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $logo_path
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $tax_id
 * @property string|null $tax_type
 * @property array|null  $address
 * @property array|null  $settings
 * @property bool        $is_default
 * @property bool        $is_active
 */
class Company extends Model
{
    use SoftDeletes;

    protected $table = 'ptah_companies';

    protected $fillable = [
        'name',
        'label',
        'slug',
        'logo_path',
        'email',
        'phone',
        'tax_id',
        'tax_type',
        'address',
        'settings',
        'is_default',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'address'    => 'array',
        'settings'   => 'array',
        'is_default' => 'boolean',
        'is_active'  => 'boolean',
    ];

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    // ─────────────────────────────────────────
    // Relacionamentos
    // ─────────────────────────────────────────

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'company_id');
    }

    // ─────────────────────────────────────────
    // Acessores / Helpers
    // ─────────────────────────────────────────

    /**
     * Retorna o label para exibição no company switcher.
     * Prioridade: label configurado → 4 primeiras letras maiúsculas do nome.
     */
    public function getLabelDisplay(): string
    {
        if (! empty($this->label)) {
            return strtoupper($this->label);
        }

        // Gera iniciais de até 4 chars: "Acme Corp" → "AC"
        $words = preg_split('/\s+/', trim($this->name));
        $initials = '';
        foreach ($words as $word) {
            if (strlen($initials) >= 4) break;
            $initials .= strtoupper(mb_substr($word, 0, 1));
        }

        return $initials ?: strtoupper(mb_substr($this->name, 0, 4));
    }

    /**
     * Retorna a URL pública do logo ou um placeholder.
     */
    public function getLogoUrl(): string
    {
        if ($this->logo_path) {
            $disk = config('ptah.company.logo_disk', 'public');
            return Storage::disk($disk)->url($this->logo_path);
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=0d6efd&color=fff&size=64';
    }

    /**
     * Retorna um campo do JSON de endereço.
     */
    public function getAddressField(string $key, mixed $default = null): mixed
    {
        return data_get($this->address, $key, $default);
    }

    /**
     * Retorna / define uma configuração customizada da empresa.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    // ─────────────────────────────────────────
    // Boot
    // ─────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        // Auto-gera slug a partir do nome, se não fornecido
        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }
}
