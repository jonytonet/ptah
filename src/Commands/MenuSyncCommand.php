<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * MenuSyncCommand — Sincroniza MenuRegistry.php com a tabela menus.
 *
 * Lê o arquivo MenuRegistry.php gerado automaticamente pelo ptah:forge
 * e popula a tabela `menus` com a estrutura hierárquica.
 *
 * Uso:
 *   php artisan ptah:menu-sync           # Adiciona novos menus (preserva existentes)
 *   php artisan ptah:menu-sync --fresh   # Limpa tabela e recria tudo
 *
 * @author Ptah Team
 * @since 1.0.0
 */
class MenuSyncCommand extends Command
{
    protected $signature = 'ptah:menu-sync
        {--fresh : Clear menus table before syncing (destructive)}';

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
            $this->clearMenusTable();
        }

        $this->newLine();
        $this->components->info('Syncing menu structure...');
        $this->newLine();

        // Skip dashboard sync — sidebar injects it hardcoded
        // $dashboardId = $this->syncDashboard($registry);

        // Sync flat root links (no group)
        $flatCount = 0;
        foreach ($registry['links'] ?? [] as $link) {
            $this->syncFlatLink($link);
            $flatCount++;
        }

        // Sync groups
        $groupCount = 0;
        $linkCount  = 0;

        foreach ($registry['groups'] ?? [] as $groupKey => $group) {
            $groupId = $this->syncGroup($groupKey, $group);
            $groupCount++;

            // Sync links inside group
            foreach ($group['links'] ?? [] as $link) {
                $this->syncLink($groupId, $link);
                $linkCount++;
            }
        }

        // Summary
        $this->newLine();
        $this->components->info("✔ Menu synced successfully!");
        $this->line("  <fg=gray>Groups: {$groupCount} | Links: {$linkCount} | Flat links: {$flatCount}</>");
        $this->line("  <fg=gray>(Dashboard is hardcoded in sidebar)</>");
        $this->newLine();
        $this->line("  <fg=blue>→ Refresh your browser to see the updated menu.</>");

        return self::SUCCESS;
    }

    /**
     * Limpa a tabela menus (modo destrutivo).
     */
    private function clearMenusTable(): void
    {
        $this->components->warn('Clearing menus table...');
        DB::table('menus')->delete();
        DB::statement('DELETE FROM sqlite_sequence WHERE name="menus"'); // Reset auto-increment (SQLite)
    }

    /**
     * Sincroniza o dashboard (link único no topo).
     *
     * @param array $registry Array do MenuRegistry
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
     * @param array $link Link data
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
                'text'       => $link['text'],
                'icon'       => $link['icon'],
                'link_order' => $link['order'],
                'is_active'  => true,
                'updated_at' => now(),
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table('menus')->insertGetId([
            'parent_id'  => null,
            'text'       => $link['text'],
            'url'        => $link['url'],
            'icon'       => $link['icon'],
            'type'       => 'menuLink',
            'target'     => '_self',
            'link_order' => $link['order'],
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Sincroniza um grupo (menuGroup).
     *
     * @param string $groupKey Chave do grupo (ex: 'health')
     * @param array $group Dados do grupo
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
     * @param int $parentId ID do grupo pai
     * @param array $link Dados do link
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
