<?php

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Ptah\Commands\Config\ModelIntrospector;
use Ptah\Commands\Config\Parsers\ColumnParser;
use Ptah\Commands\Config\Parsers\ActionParser;
use Ptah\Commands\Config\Parsers\FilterParser;
use Ptah\Commands\Config\Parsers\StyleParser;
use Ptah\Commands\Config\Parsers\JoinParser;
use Ptah\Commands\Config\Parsers\GeneralParser;
use Ptah\Commands\Config\Wizards\ColumnWizard;
use Ptah\Commands\Config\Wizards\ActionWizard;
use Ptah\Commands\Config\Wizards\FilterWizard;
use Ptah\Commands\Config\Wizards\StyleWizard;
use Ptah\Commands\Config\Wizards\JoinWizard;
use Ptah\Commands\Config\Wizards\GeneralWizard;
use Ptah\Commands\Config\Formatters\TableFormatter;
use Ptah\Enums\CrudConfigEnums;
use Ptah\Exceptions\ConfigValidationException;
use Ptah\Services\Validation\ConfigSchemaValidator;
use Ptah\Services\Validation\Formatters\CliErrorFormatter;

class ConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ptah:config {model : The model class name (e.g., App\Models\Product)}
                            {--column=* : Add/update column configuration: field:type:modifier:option=value}
                            {--action=* : Add custom action: name:type:value:icon=icon:color=color}
                            {--filter=* : Add custom filter: field:type:operator:label=Label}
                            {--style=* : Add style rule: field:operator:value:css}
                            {--join=* : Add table join: type:table:on:select=field1,field2}
                            {--set=* : Set general config: key=value}
                            {--permission=* : Set permission: action=permission}
                            {--list : List current configuration}
                            {--reset : Reset configuration to defaults}
                            {--import= : Import configuration from JSON file}
                            {--export= : Export configuration to JSON file}
                            {--non-interactive : Skip wizard questions, use only provided options}
                            {--force : Force overwrite existing configuration}
                            {--dry-run : Show what would be changed without saving}
                            {--only=* : Process only specific sections (columns,actions,filters,styles,joins,general,permissions)}
                            {--skip=* : Skip specific sections}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Configure CRUD settings for a model via CLI';

    protected ModelIntrospector $introspector;
    protected ColumnParser $columnParser;
    protected ActionParser $actionParser;
    protected FilterParser $filterParser;
    protected StyleParser $styleParser;
    protected JoinParser $joinParser;
    protected GeneralParser $generalParser;
    protected ConfigSchemaValidator $validator;
    protected CliErrorFormatter $errorFormatter;
    protected array $config = [];

    public function __construct()
    {
        parent::__construct();
        $this->introspector = new ModelIntrospector();
        $this->columnParser = new ColumnParser();
        $this->actionParser = new ActionParser();
        $this->filterParser = new FilterParser();
        $this->styleParser = new StyleParser();
        $this->joinParser = new JoinParser();
        $this->generalParser = new GeneralParser();
        $this->validator = new ConfigSchemaValidator();
        $this->errorFormatter = new CliErrorFormatter();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $modelClass = $this->argument('model');

        // Validate model
        if (!$this->introspector->validateModelClass($modelClass)) {
            $this->error("Model class '{$modelClass}' not found or is not a valid Eloquent model.");
            return 1;
        }

        // Handle special actions
        if ($this->option('list')) {
            return $this->listConfiguration($modelClass);
        }

        if ($this->option('reset')) {
            return $this->resetConfiguration($modelClass);
        }

        if ($import = $this->option('import')) {
            return $this->importConfiguration($modelClass, $import);
        }

        if ($export = $this->option('export')) {
            return $this->exportConfiguration($modelClass, $export);
        }

        // Load existing configuration
        $this->config = $this->loadConfiguration($modelClass);

        // Determine mode: interactive or declarative
        $hasOptions = $this->option('column') 
            || $this->option('action') 
            || $this->option('filter') 
            || $this->option('style') 
            || $this->option('join') 
            || $this->option('set') 
            || $this->option('permission');

        if ($this->option('non-interactive') || $hasOptions) {
            // Declarative mode
            $this->info("Processing declarative configuration for {$modelClass}...");
            $this->processDeclarativeMode($modelClass);
        } else {
            // Interactive mode
            $this->info("Starting interactive configuration wizard for {$modelClass}...");
            $this->processInteractiveMode($modelClass);
        }

        // Save if not dry-run
        if (!$this->option('dry-run')) {
            $this->saveConfiguration($modelClass, $this->config);
            $this->info("✓ Configuration saved successfully!");
        } else {
            $this->warn("Dry-run mode: No changes were saved.");
        }

        return 0;
    }

    /**
     * Process declarative mode using command options
     */
    protected function processDeclarativeMode(string $modelClass): void
    {
        $sections = $this->getSectionsToProcess();

        // Process columns
        if (in_array('columns', $sections) && $this->option('column')) {
            $this->info("Processing columns...");
            foreach ($this->option('column') as $columnConfig) {
                $parsed = $this->parseColumnOption($columnConfig);
                $this->config['cols'][] = $parsed;
            }
        }

        // Process actions
        if (in_array('actions', $sections) && $this->option('action')) {
            $this->info("Processing actions...");
            foreach ($this->option('action') as $actionConfig) {
                $parsed = $this->parseActionOption($actionConfig);
                $this->config['actions'][] = $parsed;
            }
        }

        // Process filters
        if (in_array('filters', $sections) && $this->option('filter')) {
            $this->info("Processing filters...");
            foreach ($this->option('filter') as $filterConfig) {
                $parsed = $this->parseFilterOption($filterConfig);
                $this->config['filters'][] = $parsed;
            }
        }

        // Process styles
        if (in_array('styles', $sections) && $this->option('style')) {
            $this->info("Processing styles...");
            foreach ($this->option('style') as $styleConfig) {
                $parsed = $this->parseStyleOption($styleConfig);
                $this->config['styles'][] = $parsed;
            }
        }

        // Process joins
        if (in_array('joins', $sections) && $this->option('join')) {
            $this->info("Processing joins...");
            foreach ($this->option('join') as $joinConfig) {
                $parsed = $this->parseJoinOption($joinConfig);
                $this->config['joins'][] = $parsed;
            }
        }

        // Process general settings
        if (in_array('general', $sections) && $this->option('set')) {
            $this->info("Processing general settings...");
            foreach ($this->option('set') as $setting) {
                [$key, $value] = explode('=', $setting, 2);
                $this->config[$key] = $this->castValue($value);
            }
        }

        // Process permissions
        if (in_array('permissions', $sections) && $this->option('permission')) {
            $this->info("Processing permissions...");
            foreach ($this->option('permission') as $permission) {
                [$action, $perm] = explode('=', $permission, 2);
                $this->config['permissions'][$action] = $perm;
            }
        }

        $this->displayConfigSummary();
    }

    /**
     * Process interactive mode with wizards
     */
    protected function processInteractiveMode(string $modelClass): void
    {
        $sections = $this->getSectionsToProcess();

        $this->info("Starting configuration wizard for {$modelClass}");
        $this->newLine();

        // Configure columns
        if (in_array('columns', $sections)) {
            $this->info("📋 Column Configuration");
            $columnWizard = new ColumnWizard($this, $this->introspector);
            
            while ($this->confirm("Configure a column?", true)) {
                $column = $columnWizard->run($modelClass);
                
                if ($column) {
                    $this->config['cols'][] = $column;
                    $this->info("✓ Column '{$column['colsNomeFisico']}' added.");
                }
            }
        }

        // Configure actions
        if (in_array('actions', $sections) && $this->confirm("Configure custom actions?", false)) {
            $this->newLine();
            $this->info("⚡ Action Configuration");
            $actionWizard = new ActionWizard($this);
            
            while ($this->confirm("Add an action?", true)) {
                $action = $actionWizard->run();
                
                if ($action) {
                    $this->config['actions'][] = $action;
                    $this->info("✓ Action '{$action['actionName']}' added.");
                }
            }
        }

        // Configure filters
        if (in_array('filters', $sections) && $this->confirm("Configure custom filters?", false)) {
            $this->newLine();
            $this->info("🔍 Filter Configuration");
            $filterWizard = new FilterWizard($this);
            
            while ($this->confirm("Add a filter?", true)) {
                $filter = $filterWizard->run();
                
                if ($filter) {
                    $this->config['filters'][] = $filter;
                    $this->info("✓ Filter '{$filter['colsFilterField']}' added.");
                }
            }
        }

        // Configure styles
        if (in_array('styles', $sections) && $this->confirm("Configure conditional styles?", false)) {
            $this->newLine();
            $this->info("🎨 Style Configuration");
            $styleWizard = new StyleWizard($this);
            
            while ($this->confirm("Add a style rule?", true)) {
                $style = $styleWizard->run();
                
                if ($style) {
                    $this->config['styles'][] = $style;
                    $this->info("✓ Style rule added.");
                }
            }
        }

        // Configure joins
        if (in_array('joins', $sections) && $this->confirm("Configure table JOINs?", false)) {
            $this->newLine();
            $this->info("🔗 JOIN Configuration");
            $joinWizard = new JoinWizard($this);
            
            while ($this->confirm("Add a JOIN?", true)) {
                $join = $joinWizard->run();
                
                if ($join) {
                    $this->config['joins'][] = $join;
                    $this->info("✓ JOIN with '{$join['joinTable']}' added.");
                }
            }
        }

        // Configure general settings
        if (in_array('general', $sections) && $this->confirm("Configure general settings?", true)) {
            $this->newLine();
            $generalWizard = new GeneralWizard($this);
            $generalSettings = $generalWizard->runGeneralSettings($this->config);
            $this->config = array_merge($this->config, $generalSettings);
            $this->info("✓ General settings configured.");
        }

        // Configure permissions
        if (in_array('permissions', $sections) && $this->confirm("Configure permissions?", false)) {
            $this->newLine();
            $generalWizard = new GeneralWizard($this);
            $this->config['permissions'] = $generalWizard->runPermissions($this->config['permissions'] ?? []);
            $this->info("✓ Permissions configured.");
        }

        $this->displayConfigSummary();
    }

    /**
     * Parse column option
     */
    protected function parseColumnOption(string $config): array
    {
        return $this->columnParser->parse($config);
    }

    /**
     * Parse action option
     */
    protected function parseActionOption(string $config): array
    {
        return $this->actionParser->parse($config);
    }

    /**
     * Parse filter option
     */
    protected function parseFilterOption(string $config): array
    {
        return $this->filterParser->parse($config);
    }

    /**
     * Parse style option
     */
    protected function parseStyleOption(string $config): array
    {
        return $this->styleParser->parse($config);
    }

    /**
     * Parse join option
     */
    protected function parseJoinOption(string $config): array
    {
        return $this->joinParser->parse($config);
    }

    /**
     * Get sections to process based on --only and --skip options
     */
    protected function getSectionsToProcess(): array
    {
        $all = ['columns', 'actions', 'filters', 'styles', 'joins', 'general', 'permissions'];

        if ($only = $this->option('only')) {
            return array_intersect($all, $only);
        }

        if ($skip = $this->option('skip')) {
            return array_diff($all, $skip);
        }

        return $all;
    }

    /**
     * Load existing configuration from database
     */
    protected function loadConfiguration(string $modelClass): array
    {
        $config = DB::table('crud_configs')
            ->where('model', $modelClass)
            ->first();

        if ($config) {
            return json_decode($config->config, true);
        }

        return $this->getDefaultConfiguration($modelClass);
    }

    /**
     * Get default configuration
     */
    protected function getDefaultConfiguration(string $modelClass): array
    {
        return [
            'cols' => [],
            'actions' => [],
            'filters' => [],
            'styles' => [],
            'joins' => [],
            'permissions' => [],
            'cacheEnabled' => true,
            'cacheTime' => 60,
            'paginationEnabled' => true,
            'itemsPerPage' => 10,
        ];
    }

    /**
     * Save configuration to database
     */
    protected function saveConfiguration(string $modelClass, array $config): void
    {
        try {
            // Validate configuration before saving
            $this->validator->validate($config, $modelClass);

            DB::table('crud_configs')->updateOrInsert(
                ['model' => $modelClass],
                [
                    'config' => json_encode($config),
                    'updated_at' => now(),
                ]
            );

            // Clear cache
            cache()->forget("crud_config_{$modelClass}");
        } catch (ConfigValidationException $e) {
            // Format error with CLI box drawing
            $this->newLine();
            $this->line($this->errorFormatter->format($e));
            $this->newLine();
            
            // Exit with error code
            exit(1);
        }
    }

    /**
     * List current configuration
     */
    protected function listConfiguration(string $modelClass): int
    {
        $config = $this->loadConfiguration($modelClass);

        $formatter = new TableFormatter($this->output);
        $formatter->format($config, $modelClass);

        return 0;
    }

    /**
     * Reset configuration
     */
    protected function resetConfiguration(string $modelClass): int
    {
        if (!$this->confirm("Are you sure you want to reset all configuration for {$modelClass}?")) {
            $this->info("Reset cancelled.");
            return 0;
        }

        DB::table('crud_configs')->where('model', $modelClass)->delete();
        cache()->forget("crud_config_{$modelClass}");

        $this->info("✓ Configuration reset successfully!");
        return 0;
    }

    /**
     * Import configuration from file
     */
    protected function importConfiguration(string $modelClass, string $file): int
    {
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $config = json_decode(file_get_contents($file), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON file: " . json_last_error_msg());
            return 1;
        }

        $this->saveConfiguration($modelClass, $config);
        $this->info("✓ Configuration imported successfully from {$file}");

        return 0;
    }

    /**
     * Export configuration to file
     */
    protected function exportConfiguration(string $modelClass, string $file): int
    {
        $config = $this->loadConfiguration($modelClass);

        file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $this->info("✓ Configuration exported successfully to {$file}");

        return 0;
    }

    /**
     * Display configuration summary
     */
    protected function displayConfigSummary(): void
    {
        $this->newLine();
        $this->info("Configuration Summary:");
        $this->line("- Columns: " . count($this->config['cols'] ?? []));
        $this->line("- Actions: " . count($this->config['actions'] ?? []));
        $this->line("- Filters: " . count($this->config['filters'] ?? []));
        $this->line("- Styles: " . count($this->config['styles'] ?? []));
        $this->line("- Joins: " . count($this->config['joins'] ?? []));
        $this->newLine();
    }

    /**
     * Cast string value to appropriate type
     */
    protected function castValue(string $value): mixed
    {
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        if ($value === 'null') return null;
        if (is_numeric($value)) return $value + 0;
        
        return $value;
    }
}
