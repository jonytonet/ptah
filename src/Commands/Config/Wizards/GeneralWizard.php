<?php

namespace Ptah\Commands\Config\Wizards;

use Illuminate\Console\Command;
use Ptah\Enums\CrudConfigEnums;

class GeneralWizard
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * Run interactive wizard for general settings
     */
    public function runGeneralSettings(?array $existingConfig = null): array
    {
        $this->command->info("=== General Settings Configuration ===");
        $this->command->newLine();

        $config = [];

        // Cache settings
        $this->command->info("--- Cache Settings ---");
        $config['cacheEnabled'] = $this->command->confirm("Enable cache?", $existingConfig['cacheEnabled'] ?? true);
        
        if ($config['cacheEnabled']) {
            $config['cacheTime'] = (int) $this->command->ask("Cache time (minutes)", $existingConfig['cacheTime'] ?? 60);
        }

        // Pagination settings
        $this->command->newLine();
        $this->command->info("--- Pagination Settings ---");
        $config['paginationEnabled'] = $this->command->confirm("Enable pagination?", $existingConfig['paginationEnabled'] ?? true);
        
        if ($config['paginationEnabled']) {
            $config['itemsPerPage'] = (int) $this->command->ask("Items per page", $existingConfig['itemsPerPage'] ?? 10);
            $config['paginationOptions'] = $this->command->ask(
                "Page size options (comma-separated)",
                $existingConfig['paginationOptions'] ?? '10,25,50,100'
            );
            $config['paginationOptions'] = array_map('intval', explode(',', str_replace(' ', '', $config['paginationOptions'])));
        }

        // Search settings
        $this->command->newLine();
        $this->command->info("--- Search Settings ---");
        $config['searchEnabled'] = $this->command->confirm("Enable global search?", $existingConfig['searchEnabled'] ?? true);
        
        if ($config['searchEnabled']) {
            $config['searchPlaceholder'] = $this->command->ask("Search placeholder", $existingConfig['searchPlaceholder'] ?? 'Search...');
        }

        // Export settings
        $this->command->newLine();
        $this->command->info("--- Export Settings ---");
        $config['exportEnabled'] = $this->command->confirm("Enable export?", $existingConfig['exportEnabled'] ?? true);
        
        if ($config['exportEnabled']) {
            $formats = ['pdf', 'excel', 'csv'];
            $selectedFormats = [];
            
            foreach ($formats as $format) {
                if ($this->command->confirm("  Enable {$format} export?", true)) {
                    $selectedFormats[] = $format;
                }
            }
            
            $config['exportFormats'] = $selectedFormats;
            
            $config['exportOrientation'] = $this->command->choice(
                "PDF orientation",
                CrudConfigEnums::ORIENTATIONS,
                $existingConfig['exportOrientation'] ?? 'landscape'
            );
        }

        // UI Settings
        $this->command->newLine();
        $this->command->info("--- UI Settings ---");
        $config['theme'] = $this->command->choice(
            "Theme",
            CrudConfigEnums::THEMES,
            $existingConfig['theme'] ?? 'light'
        );

        $config['showRowNumbers'] = $this->command->confirm("Show row numbers?", $existingConfig['showRowNumbers'] ?? true);
        $config['compactMode'] = $this->command->confirm("Compact mode?", $existingConfig['compactMode'] ?? false);
        $config['striped'] = $this->command->confirm("Striped rows?", $existingConfig['striped'] ?? true);
        $config['hover'] = $this->command->confirm("Hover effect?", $existingConfig['hover'] ?? true);

        // Soft deletes
        $this->command->newLine();
        $config['softDeletes'] = $this->command->confirm("Use soft deletes?", $existingConfig['softDeletes'] ?? false);
        
        if ($config['softDeletes']) {
            $config['showTrashed'] = $this->command->confirm("Show trashed items by default?", $existingConfig['showTrashed'] ?? false);
        }

        $this->previewGeneralSettings($config);

        return $config;
    }

    /**
     * Run interactive wizard for permissions
     */
    public function runPermissions(?array $existingPermissions = null): array
    {
        $this->command->info("=== Permissions Configuration ===");
        $this->command->newLine();

        $permissions = [];
        $actions = ['list', 'view', 'create', 'edit', 'delete', 'export', 'import', 'restore', 'forceDelete'];

        foreach ($actions as $action) {
            if ($this->command->confirm("Set permission for '{$action}' action?", false)) {
                $permissions[$action] = $this->command->ask("Permission string", $existingPermissions[$action] ?? "{$action}.resource");
            }
        }

        // Custom permissions
        if ($this->command->confirm("Add custom permission?", false)) {
            while (true) {
                $key = $this->command->ask("Permission key (or empty to finish)");
                
                if (!$key) {
                    break;
                }
                
                $value = $this->command->ask("Permission string");
                $permissions[$key] = $value;
            }
        }

        $this->previewPermissions($permissions);

        return $permissions;
    }

    /**
     * Preview general settings
     */
    protected function previewGeneralSettings(array $config): void
    {
        $this->command->newLine();
        $this->command->info("=== General Settings Preview ===");
        $this->command->table(
            ['Setting', 'Value'],
            collect($config)->map(fn($value, $key) => [
                $key,
                is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'enabled' : 'disabled') : $value)
            ])->toArray()
        );
        $this->command->newLine();
    }

    /**
     * Preview permissions
     */
    protected function previewPermissions(array $permissions): void
    {
        $this->command->newLine();
        $this->command->info("=== Permissions Preview ===");
        
        if (empty($permissions)) {
            $this->command->warn("No permissions configured.");
        } else {
            $this->command->table(
                ['Action', 'Permission'],
                collect($permissions)->map(fn($value, $key) => [$key, $value])->toArray()
            );
        }
        
        $this->command->newLine();
    }
}
