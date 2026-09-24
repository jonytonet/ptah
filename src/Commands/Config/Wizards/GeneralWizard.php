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
     * Interactive general settings — only what BaseCrud reads.
     *
     * This wizard used to ask fifteen questions (cache, pagination on/off,
     * search on/off and placeholder, striped, hover, row numbers, soft
     * deletes…) and write every answer to a top-level key no code reads: the
     * session looked like configuration and changed nothing. It now asks for
     * the settings that exist, and returns them as dotted paths
     * (`uiPreferences.perPage`) for the caller to data_set().
     *
     * @return array<string, mixed> path => value
     */
    public function runGeneralSettings(?array $existingConfig = null): array
    {
        $existingConfig ??= [];
        $this->command->info('=== General Settings Configuration ===');
        $this->command->newLine();

        $config = [];

        $config['displayName'] = (string) $this->command->ask('Screen title (displayName)', $existingConfig['displayName'] ?? null);
        if ($config['displayName'] === '') {
            unset($config['displayName']);
        }

        $config['uiPreferences.perPage'] = (int) $this->command->ask('Default rows per page', (string) data_get($existingConfig, 'uiPreferences.perPage', config('ptah.crud.per_page', 25)));
        $config['uiPreferences.compactMode'] = $this->command->confirm('Compact rows by default?', (bool) data_get($existingConfig, 'uiPreferences.compactMode', false));

        $config['exportConfig.enabled'] = $this->command->confirm('Enable export?', (bool) data_get($existingConfig, 'exportConfig.enabled', false));

        if ($config['exportConfig.enabled']) {
            $formats = $this->command->choice('Export formats (comma-separated)', ['excel', 'pdf'], 0, null, true);
            $config['exportConfig.formats'] = array_values((array) $formats);
            $config['exportConfig.orientation'] = $this->command->choice(
                'PDF orientation',
                CrudConfigEnums::ORIENTATIONS,
                data_get($existingConfig, 'exportConfig.orientation', 'landscape')
            );
            $config['exportConfig.maxRows'] = (int) $this->command->ask('Maximum exportable rows', (string) data_get($existingConfig, 'exportConfig.maxRows', 10000));
        }

        $this->previewGeneralSettings($config);

        return $config;
    }

    /**
     * Run interactive wizard for permissions
     */
    public function runPermissions(?array $existingPermissions = null): array
    {
        $this->command->info('=== Permissions Configuration ===');
        $this->command->newLine();

        $permissions = [];
        // As que HasCrudForm::getEffectivePermissions() le; 'list', 'view', 'import'
        // e 'forceDelete' eram perguntadas e ignoradas.
        $actions = ['create', 'edit', 'delete', 'export', 'restore'];

        foreach ($actions as $action) {
            if ($this->command->confirm("Set permission for '{$action}' action?", false)) {
                $permissions[$action] = $this->command->ask('Permission string', $existingPermissions[$action] ?? "{$action}.resource");
            }
        }

        // Custom permissions
        if ($this->command->confirm('Add custom permission?', false)) {
            while (true) {
                $key = $this->command->ask('Permission key (or empty to finish)');

                if (! $key) {
                    break;
                }

                $value = $this->command->ask('Permission string');
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
        $this->command->info('=== General Settings Preview ===');
        $this->command->table(
            ['Setting', 'Value'],
            collect($config)->map(fn ($value, $key) => [
                $key,
                is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'enabled' : 'disabled') : $value),
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
        $this->command->info('=== Permissions Preview ===');

        if (empty($permissions)) {
            $this->command->warn('No permissions configured.');
        } else {
            $this->command->table(
                ['Action', 'Permission'],
                collect($permissions)->map(fn ($value, $key) => [$key, $value])->toArray()
            );
        }

        $this->command->newLine();
    }
}
