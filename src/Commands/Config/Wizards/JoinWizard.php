<?php

namespace Ptah\Commands\Config\Wizards;

use Illuminate\Console\Command;
use Ptah\Enums\CrudConfigEnums;

class JoinWizard
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    /**
     * Run interactive wizard to configure a JOIN
     */
    public function run(?array $existingJoin = null): ?array
    {
        $this->command->info("=== JOIN Configuration Wizard ===");
        $this->command->newLine();

        $table = $this->command->ask("Table to join", $existingJoin['joinTable'] ?? null);
        
        if (!$table) {
            $this->command->warn("Table name is required.");
            return null;
        }

        $type = $this->command->choice(
            "JOIN type",
            CrudConfigEnums::JOIN_TYPES,
            $existingJoin['joinType'] ?? 'left'
        );

        $this->command->info("Configure JOIN condition:");
        $leftColumn = $this->command->ask("Left table column", $existingJoin['joinLeftColumn'] ?? '');
        $rightColumn = $this->command->ask("Right table column (joined table)", $existingJoin['joinRightColumn'] ?? "{$table}.id");

        $distinct = $this->command->confirm("Use DISTINCT?", $existingJoin['joinDistinct'] ?? false);

        $this->command->info("Enter columns to select from joined table (comma-separated):");
        $selectColumns = $this->command->ask("Columns", $existingJoin['joinSelect'] ?? '');

        $join = [
            'joinTable' => $table,
            'joinType' => $type,
            'joinLeftColumn' => $leftColumn,
            'joinRightColumn' => $rightColumn,
            'joinDistinct' => $distinct,
            'joinSelect' => $selectColumns ? explode(',', str_replace(' ', '', $selectColumns)) : [],
        ];

        // Additional WHERE conditions
        if ($this->command->confirm("Add WHERE condition to JOIN?", false)) {
            $join['joinWhere'] = $this->command->ask("WHERE condition (e.g., {$table}.active = 1)");
        }

        $this->previewJoin($join);

        if (!$this->command->confirm("Save this JOIN configuration?", true)) {
            return $this->run($join);
        }

        return $join;
    }

    /**
     * Preview JOIN configuration
     */
    protected function previewJoin(array $join): void
    {
        $this->command->newLine();
        $this->command->info("=== JOIN Preview ===");
        
        $sql = strtoupper($join['joinType']) . " JOIN {$join['joinTable']} ON {$join['joinLeftColumn']} = {$join['joinRightColumn']}";
        
        if (!empty($join['joinWhere'])) {
            $sql .= " AND {$join['joinWhere']}";
        }
        
        $this->command->line("SQL: " . $sql);
        $this->command->newLine();
        
        $this->command->table(
            ['Property', 'Value'],
            collect($join)->map(fn($value, $key) => [
                $key,
                is_array($value) ? implode(', ', $value) : (is_bool($value) ? ($value ? 'true' : 'false') : $value)
            ])->toArray()
        );
        $this->command->newLine();
    }
}
