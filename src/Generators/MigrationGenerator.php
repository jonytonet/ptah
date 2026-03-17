<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Generates the Migration file for the entity.
 *
 * Stub: migration.stub
 * Placeholders: table, columns
 */
class MigrationGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        $label = "Migration [create_{$context->table}_table]";

        // Migrations are immutable database artefacts — never overwrite.
        // If any *_create_{table}_table.php file already exists, skip it
        // regardless of --force. This protects against the scenario:
        //   1. ptah:forge Product        (web)  → migration created
        //   2. ptah:forge Product --api --force → must NOT recreate the migration
        $existing = glob(database_path("migrations/*_create_{$context->table}_table.php")) ?: [];
        if (! empty($existing)) {
            // When --no-soft-deletes is set but the file was already created (e.g. from a
            // previous interrupted run), the existing migration may still contain softDeletes().
            // The developer must verify and remove it manually — ptah never overwrites migrations.
            if (! $context->withSoftDeletes) {
                return GeneratorResult::skipped(
                    $label . ' [⚠ verify: softDeletes() may still be present]',
                    $existing[0]
                );
            }
            return GeneratorResult::skipped($label, $existing[0]);
        }

        $filename = "{$context->timestamp}_create_{$context->table}_table.php";
        $path     = database_path("migrations/{$filename}");

        // Audit columns: created_by / updated_by always; deleted_by only with softDeletes
        $auditCols  = "            \$table->unsignedBigInteger('created_by')->nullable()->index();\n";
        $auditCols .= "            \$table->unsignedBigInteger('updated_by')->nullable()->index();\n";

        if ($context->withSoftDeletes) {
            $auditCols .= "            \$table->unsignedBigInteger('deleted_by')->nullable()->index();\n";
        }

        return $this->writeFile(
            path: $path,
            stub: 'migration',
            replacements: [
                'table'         => $context->table,
                'columns'       => $context->migrationColumns(),
                'audit_columns' => $auditCols,
            ],
            force: $context->force,
            labelOverride: $label,
        );
    }

    protected function label(): string
    {
        return 'Migration';
    }
}
