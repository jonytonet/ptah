<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ptah\Traits\HasAuditFields;

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
    use SoftDeletes, HasAuditFields;

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
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
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
    // Relationships
    // ─────────────────────────────────────────

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'company_id');
    }

    // ─────────────────────────────────────────
    // Accessors / Helpers
    // ─────────────────────────────────────────

    /**
     * Returns the label for display in the company switcher.
     * Priority: configured label → first 4 uppercase letters of the name.
     */
    public function getLabelDisplay(): string
    {
        if (! empty($this->label)) {
            return strtoupper($this->label);
        }

        // Generates initials up to 4 chars: "Acme Corp" → "AC"
        $words = preg_split('/\s+/', trim($this->name));
        $initials = '';
        foreach ($words as $word) {
            if (strlen($initials) >= 4) break;
            $initials .= strtoupper(mb_substr($word, 0, 1));
        }

        return $initials ?: strtoupper(mb_substr($this->name, 0, 4));
    }

    /**
     * Returns the public URL of the logo or a placeholder.
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
     * Returns a field from the address JSON.
     */
    public function getAddressField(string $key, mixed $default = null): mixed
    {
        return data_get($this->address, $key, $default);
    }

    /**
     * Returns / sets a custom company setting.
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

        // Auto-generates slug from the name, if not provided
        static::creating(function (self $model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }
}
