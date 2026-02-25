<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Gera o arquivo de Migration da entidade.
 *
 * Stub: migration.stub
 * Placeholders: table, columns
 */
class MigrationGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $filename = "{$context->timestamp}_create_{$context->table}_table.php";
        $path     = database_path("migrations/{$filename}");

        return $this->writeFile(
            path: $path,
            stub: 'migration',
            replacements: [
                'table'   => $context->table,
                'columns' => $context->migrationColumns(),
            ],
            force: $context->force,
            labelOverride: "Migration [create_{$context->table}_table]",
        );
    }

    protected function label(): string
    {
        return 'Migration';
    }
}
