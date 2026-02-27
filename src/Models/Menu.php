<?php

declare(strict_types=1);

namespace Ptah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Menu extends Model
{
    use SoftDeletes;

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
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'link_order' => 'integer',
        'parent_id'  => 'integer',
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
     * Retorna a árvore de menus pré-processada para a sidebar.
     * Formato compatível com forge-sidebar e o exemplo do erp-system/Menu.php.
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
     * Invalida o cache do menu (chamar após salvar/deletar itens).
     */
    public static function clearCache(): void
    {
        Cache::forget('ptah_menu_tree');
    }

    /**
     * Constrói a estrutura hierárquica recursivamente até max_depth.
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
            // Compatibilidade com o padrão do erp-system (label/icon string)
            $item['label'] = $item['text'];
            $item['match'] = $item['url'] ? ltrim($item['url'], '/') . '*' : null;
            return $item;
        }, array_values($items));
    }
}
