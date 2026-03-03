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
        $label = "Migration [create_{$context->table}_table]";

        // Migrations são artefatos imutáveis de banco — nunca sobrescrever.
        // Se já existe qualquer arquivo *_create_{table}_table.php, ignoramos
        // independentemente do --force. Isso protege contra o cenário:
        //   1. ptah:forge Product        (web)  → migration criada
        //   2. ptah:forge Product --api --force → NÃO deve recriar a migration
        $existing = glob(database_path("migrations/*_create_{$context->table}_table.php")) ?: [];
        if (! empty($existing)) {
            return GeneratorResult::skipped($label, $existing[0]);
        }

        $filename = "{$context->timestamp}_create_{$context->table}_table.php";
        $path     = database_path("migrations/{$filename}");

        // Colunas de auditoria: created_by / updated_by sempre; deleted_by só com softDeletes
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
