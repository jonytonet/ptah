<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a single AI conversation thread, optionally tied to an authenticated user.
 *
 * When a user is authenticated, conversations are persisted across sessions via user_id.
 * Guest conversations fall back to session_id only.
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $session_id
 * @property string|null $title           Auto-generated from the first message
 * @property array|null  $messages        [{role: 'user'|'assistant', content: string}]
 * @property string|null $provider_used
 * @property string|null $model_used
 * @property int         $tokens_used
 */
class AiConversation extends Model
{
    protected $table = 'ptah_ai_conversations';

    protected $fillable = [
        'user_id',
        'session_id',
        'title',
        'messages',
        'provider_used',
        'model_used',
        'tokens_used',
    ];

    protected $casts = [
        'messages'    => 'array',
        'tokens_used' => 'integer',
    ];

    // ─────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────

    public function scopeBySession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
