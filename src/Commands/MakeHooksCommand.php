<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Scaffolds a class-based CrudHooks file in the host application.
 *
 * Usage:
 *   php artisan ptah:hooks ProductHooks
 *   php artisan ptah:hooks Inventory/StockHooks
 *   php artisan ptah:hooks ProductHooks --force
 */
class MakeHooksCommand extends Command
{
    protected $signature = 'ptah:hooks
        {name       : Class name in PascalCase, with optional subfolder (e.g.: ProductHooks, Inventory/StockHooks)}
        {--force    : Overwrite existing file without confirmation}';

    protected $description = 'Generate a class-based CrudHooks file in app/CrudHooks/.';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $rawName = $this->argument('name');

        // Support subfolder notation: Inventory/StockHooks or Inventory\StockHooks
        $parts     = array_values(array_filter(
            array_map('trim', preg_split('/[\\\\\\/]/', $rawName))
        ));
        $className = Str::studly((string) array_pop($parts));
        $subFolder = $parts
            ? implode(DIRECTORY_SEPARATOR, array_map([Str::class, 'studly'], $parts))
            : '';

        $baseDir  = app_path('CrudHooks' . ($subFolder ? DIRECTORY_SEPARATOR . $subFolder : ''));
        $filePath = $baseDir . DIRECTORY_SEPARATOR . $className . '.php';

        if ($this->files->exists($filePath) && ! $this->option('force')) {
            $this->components->error("File already exists: {$filePath}");
            $this->line('  Use <fg=yellow>--force</> to overwrite.');
            return self::FAILURE;
        }

        $this->files->ensureDirectoryExists($baseDir);

        $namespace = 'App\\CrudHooks'
            . ($subFolder ? '\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $subFolder) : '');

        $this->files->put($filePath, $this->buildStub($namespace, $className));

        $this->components->info("Hook class created: {$filePath}");
        $this->newLine();
        $this->line('  <fg=blue>Next steps:</>');
        $this->line("  1. Implement the methods in <fg=yellow>{$filePath}</>");
        $this->line("  2. In the CrudConfig modal, set a hook field to: <fg=green>@{$className}</>");
        $this->line("     Or with explicit method: <fg=green>@{$className}::beforeCreate</>");
        $this->newLine();

        return self::SUCCESS;
    }

    private function buildStub(string $namespace, string $className): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Ptah\Contracts\CrudHooksInterface;

class {$className} implements CrudHooksInterface
{
    /**
     * Runs before a new record is inserted.
     * \$data is passed by reference — add, modify or remove fields as needed.
     */
    public function beforeCreate(array &\$data, ?Model \$record, object \$component): void
    {
        // Example: set a default value
        // \$data['status'] = 'pending';
    }

    /**
     * Runs after a new record is successfully inserted.
     * \$record is the freshly created Eloquent model.
     */
    public function afterCreate(array &\$data, Model \$record, object \$component): void
    {
        // Example: dispatch an event
        // event(new \\App\\Events\\{$className}Created(\$record));
        Log::info('{$className}: afterCreate', ['id' => \$record->getKey()]);
    }

    /**
     * Runs before an existing record is updated.
     * \$record holds the CURRENT state; \$data has the incoming values.
     */
    public function beforeUpdate(array &\$data, Model \$record, object \$component): void
    {
        // Example: track who changed the record
        // \$data['updated_by'] = auth()->id();
    }

    /**
     * Runs after an existing record is successfully updated.
     */
    public function afterUpdate(array &\$data, Model \$record, object \$component): void
    {
        // Example: clear cache
        // cache()->forget('{$className}_' . \$record->getKey());
    }
}
PHP;
    }
}
