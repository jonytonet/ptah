<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Ptah\Models\Menu;

/**
 * MenuSyncCommand — Sincroniza MenuRegistry.php com a tabela menus.
 *
 * Lê o arquivo MenuRegistry.php gerado automaticamente pelo ptah:forge
 * e popula a tabela `menus` com a estrutura hierárquica.
 *
 * Uso:
 *   php artisan ptah:menu-sync                     # Adiciona novos menus (preserva existentes)
 *   php artisan ptah:menu-sync --prune --dry-run   # Lista o que saiu do registro
 *   php artisan ptah:menu-sync --prune             # ...e o DESATIVA (is_active = 0)
 *   php artisan ptah:menu-sync --fresh             # Limpa tabela e recria tudo
 *
 * @author Ptah Team
 *
 * @since 1.0.0
 */
class MenuSyncCommand extends Command
{
    protected $signature = 'ptah:menu-sync
        {--fresh : DELETE every row of the menus table before syncing (destructive; asks first)}
        {--force : With --fresh, skip the confirmation (scripts)}
        {--prune : Deactivate (is_active = 0, never delete) the active items that are no longer in the registry}
        {--dry-run : With --prune, only list what would be deactivated}';

    protected $description = 'Sync MenuRegistry.php with the menus database table';

    public function handle(): int
    {
        $registryPath = database_path('seeders/MenuRegistry.php');

        if (! file_exists($registryPath)) {
            $this->components->error('MenuRegistry.php not found in database/seeders/');
            $this->line('  <fg=yellow>→ Run <fg=cyan>ptah:install</> to create the initial registry.');

            return self::FAILURE;
        }

        // Carregar registry
        $registry = require $registryPath;

        if (! is_array($registry)) {
            $this->components->error('MenuRegistry.php must return an array');

            return self::FAILURE;
        }

        // Limpar tabela se --fresh
        if ($this->option('fresh')) {
            // 1.50.0: it deleted the whole table without asking — including
            // every item created in the menu editor. --prune deactivates instead.
            if (! $this->option('force') && ! $this->confirm('--fresh DELETES every menu item, including the ones created in the menu editor. Prefer --prune. Continue?', false)) {
                $this->components->warn('Aborted — nothing deleted.');

                return self::FAILURE;
            }
            $this->clearMenusTable();
        }

        // Sem gravar nada: so o que o --prune desativaria.
        if ($this->option('prune') && $this->option('dry-run')) {
            return $this->pruneReport($registry);
        }

        $synced = [];

        $this->newLine();
        $this->components->info('Syncing menu structure...');
        $this->newLine();

        // Skip dashboard sync — sidebar injects it hardcoded
        // $dashboardId = $this->syncDashboard($registry);

        // Sync flat root links (no group)
        $flatCount = 0;
        foreach ($registry['links'] ?? [] as $link) {
            $synced[] = $this->syncFlatLink($link);
            $flatCount++;
        }

        // Sync groups
        $groupCount = 0;
        $linkCount = 0;

        foreach ($registry['groups'] ?? [] as $groupKey => $group) {
            $groupId = $this->syncGroup($groupKey, $group);
            $synced[] = $groupId;
            $groupCount++;

            // Sync links inside group
            foreach ($group['links'] ?? [] as $link) {
                $synced[] = $this->syncLink($groupId, $link);
                $linkCount++;
            }
        }

        // --prune: o que saiu do registro e DESATIVADO, nunca apagado (1.46.5).
        // Sem isto, a unica saida era o --fresh, que apaga a tabela inteira.
        $pruned = $this->option('prune') ? $this->prune($synced) : 0;

        // Sempre: o sync grava pelo query builder (sem eventos de model), e a
        // sidebar seguia com o menu antigo ate o cache vencer.
        Menu::clearCache();

        // Summary
        $this->newLine();
        $this->components->info('✔ Menu synced successfully!');
        if ($this->option('prune')) {
            $this->line("  <fg=gray>Deactivated (no longer in the registry): {$pruned}</>");
        }
        $this->line("  <fg=gray>Groups: {$groupCount} | Links: {$linkCount} | Flat links: {$flatCount}</>");
        $this->line('  <fg=gray>(Dashboard is hardcoded in sidebar)</>');
        $this->newLine();
        $this->line('  <fg=blue>→ Refresh your browser to see the updated menu.</>');

        return self::SUCCESS;
    }

    /**
     * The active items no longer in the registry: active, and not among the
     * ids this run synced. Groups count too.
     *
     * @param  list<int>  $synced
     * @return Collection<int, \stdClass>
     */
    private function stale(array $synced)
    {
        return DB::table('menus')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotIn('id', $synced === [] ? [0] : $synced)
            ->orderBy('type')->orderBy('text')
            ->get(['id', 'type', 'text', 'url']);
    }

    /** @param  list<int>  $synced */
    private function prune(array $synced): int
    {
        $stale = $this->stale($synced);
        $this->listStale($stale, 'Deactivating');

        if ($stale->isNotEmpty()) {
            DB::table('menus')->whereIn('id', $stale->pluck('id'))->update(['is_active' => false, 'updated_at' => now()]);
            $this->line('  <fg=gray>Reversible: set is_active = 1 on an item (or use the menu editor) to bring it back.</>');
        }

        return $stale->count();
    }

    /**
     * --prune --dry-run: which active items are not in the registry, without
     * writing anything. Matches the registry the way the sync does: a group by
     * its text, a link by its url (inside its group, or at the root).
     */
    private function pruneReport(array $registry): int
    {
        $keep = [];
        foreach ($registry['links'] ?? [] as $link) {
            $keep[] = DB::table('menus')->where('type', 'menuLink')->whereNull('parent_id')->where('url', $link['url'])->value('id');
        }
        foreach ($registry['groups'] ?? [] as $group) {
            $groupId = DB::table('menus')->where('type', 'menuGroup')->whereNull('parent_id')->where('text', $group['text'])->value('id');
            $keep[] = $groupId;
            foreach ($group['links'] ?? [] as $link) {
                $keep[] = $groupId ? DB::table('menus')->where('type', 'menuLink')->where('parent_id', $groupId)->where('url', $link['url'])->value('id') : null;
            }
        }

        $stale = $this->stale(array_values(array_map('intval', array_filter($keep))));
        $this->listStale($stale, 'Would deactivate (dry run, nothing written)');

        return self::SUCCESS;
    }

    private function listStale($stale, string $title): void
    {
        $this->newLine();
        if ($stale->isEmpty()) {
            $this->line('  <fg=gray>Nothing to deactivate: every active item is in the registry.</>');

            return;
        }

        $this->components->warn("{$title}: {$stale->count()} item(s) not in MenuRegistry.php");
        $this->line('  <fg=gray>(Includes items created in the menu editor that are not in the registry.)</>');
        foreach ($stale as $item) {
            $this->line("  - #{$item->id} [{$item->type}] {$item->text}".($item->url ? "  {$item->url}" : ''));
        }
    }

    /**
     * Limpa a tabela menus (modo destrutivo).
     */
    private function clearMenusTable(): void
    {
        $this->components->warn('Clearing menus table...');
        DB::table('menus')->delete();
        // Reset auto-increment only on SQLite — other drivers don't have sqlite_sequence.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DELETE FROM sqlite_sequence WHERE name="menus"');
        }
    }

    /**
     * Sincroniza o dashboard (link único no topo).
     *
     * @param  array  $registry  Array do MenuRegistry
     * @return int|null ID do menu dashboard criado ou null se não existir
     */
    private function syncDashboard(array $registry): ?int
    {
        if (! isset($registry['dashboard'])) {
            return null;
        }

        $dashboard = $registry['dashboard'];

        // Verificar se já existe
        $existing = DB::table('menus')
            ->where('type', 'menuLink')
            ->where('url', $dashboard['url'])
            ->where('parent_id', null)
            ->first();

        if ($existing) {
            // Atualizar
            DB::table('menus')->where('id', $existing->id)->update([
                'text' => $dashboard['text'],
                'icon' => $dashboard['icon'],
                'link_order' => $dashboard['order'],
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        // Criar novo
        return (int) DB::table('menus')->insertGetId([
            'parent_id' => null,
            'text' => $dashboard['text'],
            'url' => $dashboard['url'],
            'icon' => $dashboard['icon'],
            'type' => 'menuLink',
            'target' => '_self',
            'link_order' => $dashboard['order'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Syncs a flat root link (menuLink, parent_id = null).
     *
     * @param  array  $link  Link data
     * @return int ID of the created/updated record
     */
    private function syncFlatLink(array $link): int
    {
        $existing = DB::table('menus')
            ->where('type', 'menuLink')
            ->where('url', $link['url'])
            ->whereNull('parent_id')
            ->first();

        if ($existing) {
            DB::table('menus')->where('id', $existing->id)->update([
                'text' => $link['text'],
                'icon' => $link['icon'],
                'link_order' => $link['order'],
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('menus')->insertGetId([
            'parent_id' => null,
            'text' => $link['text'],
            'url' => $link['url'],
            'icon' => $link['icon'],
            'type' => 'menuLink',
            'target' => '_self',
            'link_order' => $link['order'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Sincroniza um grupo (menuGroup).
     *
     * @param  string  $groupKey  Chave do grupo (ex: 'health')
     * @param  array  $group  Dados do grupo
     * @return int ID do grupo criado/atualizado
     */
    private function syncGroup(string $groupKey, array $group): int
    {
        // Verificar se já existe (busca por texto e tipo)
        $existing = DB::table('menus')
            ->where('type', 'menuGroup')
            ->where('text', $group['text'])
            ->where('parent_id', null)
            ->first();

        if ($existing) {
            // Atualizar
            DB::table('menus')->where('id', $existing->id)->update([
                'icon' => $group['icon'],
                'link_order' => $group['order'],
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        // Criar novo grupo
        return (int) DB::table('menus')->insertGetId([
            'parent_id' => null,
            'text' => $group['text'],
            'url' => null,
            'icon' => $group['icon'],
            'type' => 'menuGroup',
            'target' => '_self',
            'link_order' => $group['order'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Sincroniza um link (menuLink) dentro de um grupo.
     *
     * @param  int  $parentId  ID do grupo pai
     * @param  array  $link  Dados do link
     * @return int ID do link criado/atualizado
     */
    private function syncLink(int $parentId, array $link): int
    {
        // Verificar se já existe (busca por parent_id + url)
        $existing = DB::table('menus')
            ->where('type', 'menuLink')
            ->where('parent_id', $parentId)
            ->where('url', $link['url'])
            ->first();

        if ($existing) {
            // Atualizar
            DB::table('menus')->where('id', $existing->id)->update([
                'text' => $link['text'],
                'icon' => $link['icon'],
                'link_order' => $link['order'],
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        // Criar novo link
        return (int) DB::table('menus')->insertGetId([
            'parent_id' => $parentId,
            'text' => $link['text'],
            'url' => $link['url'],
            'icon' => $link['icon'],
            'type' => 'menuLink',
            'target' => '_self',
            'link_order' => $link['order'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
