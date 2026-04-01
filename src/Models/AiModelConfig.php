<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ptah\Traits\HasAuditFields;

/**
 * AI Model / Provider configuration record.
 *
 * The api_key is ALWAYS stored encrypted via the 'encrypted' cast.
 * It requires a valid APP_KEY in the host application.
 *
 * @property int         $id
 * @property string      $name
 * @property string      $provider       openai|anthropic|gemini|ollama|groq|mistral
 * @property string      $model
 * @property string      $api_key        Encrypted at rest
 * @property string|null $api_endpoint
 * @property int         $max_tokens
 * @property float       $temperature
 * @property string|null $system_prompt
 * @property string|null $notes
 * @property bool        $is_active
 * @property bool        $is_default
 */
class AiModelConfig extends Model
{
    use SoftDeletes, HasAuditFields;

    protected $table = 'ptah_ai_model_configs';

    protected $fillable = [
        'name',
        'notes',
        'provider',
        'model',
        'api_key',
        'api_endpoint',
        'max_tokens',
        'temperature',
        'system_prompt',
        'is_active',
        'is_default',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'api_key'     => 'encrypted',
        'max_tokens'  => 'integer',
        'temperature' => 'float',
        'is_active'   => 'boolean',
        'is_default'  => 'boolean',
        'created_by'  => 'integer',
        'updated_by'  => 'integer',
        'deleted_by'  => 'integer',
    ];

    /** Hide encrypted key from JSON serialisation */
    protected $hidden = ['api_key'];

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
    // Helpers
    // ─────────────────────────────────────────

    /** Returns a sanitised label for display in the dropdown. */
    public function getDisplayLabel(): string
    {
        return "{$this->name} ({$this->provider} / {$this->model})";
    }
}
