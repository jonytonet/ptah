<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Ptah\Traits\HasAuditFields;

class Menu extends Model
{
    use SoftDeletes, HasAuditFields;

    public $table = 'menus';

    protected $fillable = [
        'parent_id',
        'text',
        'url',
        'icon',
        'type',
        'target',
        'link_order',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'link_order' => 'integer',
        'parent_id'  => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'deleted_by' => 'integer',
    ];

    // ── Relacionamentos ────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('link_order');
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('link_order')->orderBy('text');
    }

    // ── Tree Builder ───────────────────────────────────────────────────

    /**
     * Returns the pre-processed menu tree for the sidebar.
     * Format compatible with forge-sidebar and the erp-system/Menu.php example.
     */
    public static function getTreeForSidebar(): array
    {
        $ttl      = config('ptah.menu.cache_ttl', 300);
        $useCache = config('ptah.menu.cache', true);

        $builder = fn() => static::buildTree();

        return $useCache
            ? Cache::remember('ptah_menu_tree', $ttl, $builder)
            : $builder();
    }

    /**
     * Invalidates the menu cache (call after saving/deleting items).
     */
    public static function clearCache(): void
    {
        Cache::forget('ptah_menu_tree');
    }

    /**
     * Builds the hierarchical structure recursively up to max_depth.
     */
    private static function buildTree(): array
    {
        $maxDepth = config('ptah.menu.max_depth', 4);
        $all = static::withoutTrashed()->active()->ordered()->get()->toArray();

        return static::nestItems($all, null, $maxDepth, 0);
    }

    private static function nestItems(array $all, ?int $parentId, int $maxDepth, int $depth): array
    {
        if ($depth >= $maxDepth) {
            return [];
        }

        $items = array_filter($all, fn($item) => $item['parent_id'] === $parentId);

        return array_map(function (array $item) use ($all, $maxDepth, $depth) {
            $item['children'] = static::nestItems($all, $item['id'], $maxDepth, $depth + 1);
            // Compatibility with the erp-system pattern (label/icon string)
            $item['label'] = $item['text'];
            // Uses the exact path without wildcard — the sidebar tests exact + sub-routes (url/*)
            $item['match'] = $item['url'] ? ltrim(rtrim($item['url'], '/'), '/') : null;
            return $item;
        }, array_values($items));
    }
}
