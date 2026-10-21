<?php

namespace Ptah\Commands\Config\Wizards;

use Illuminate\Console\Command;
use Ptah\Commands\Config\Parsers\JoinParser;
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
        $this->command->info('=== JOIN Configuration Wizard ===');
        $this->command->newLine();

        // 1.50.0: writes the shape the runtime reads (type/table/first/second/
        // distinct/select) — it wrote joinTable/joinType/… that nothing read.
        $table = $this->command->ask('Table to join', $existingJoin['table'] ?? null);

        if (! $table) {
            $this->command->warn('Table name is required.');

            return null;
        }

        $type = $this->command->choice(
            'JOIN type',
            CrudConfigEnums::JOIN_TYPES,
            $existingJoin['type'] ?? 'left'
        );

        $this->command->info('Configure JOIN condition:');
        $leftColumn = $this->command->ask('Left table column', $existingJoin['first'] ?? '');
        $rightColumn = $this->command->ask('Right table column (joined table)', $existingJoin['second'] ?? "{$table}.id");

        $distinct = $this->command->confirm('Use DISTINCT?', (bool) ($existingJoin['distinct'] ?? false));

        $this->command->info('Columns to select from the joined table, comma-separated, as column:alias (e.g. suppliers.name:supplier_name):');
        $selectColumns = (string) $this->command->ask('Columns', '');

        $join = [
            'type' => $type,
            'table' => $table,
            'first' => $leftColumn,
            'second' => $rightColumn,
            'distinct' => $distinct,
            'select' => JoinParser::parseSelect($selectColumns),
        ];

        $this->previewJoin($join);

        if (! $this->command->confirm('Save this JOIN configuration?', true)) {
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
        $this->command->info('=== JOIN Preview ===');

        $sql = strtoupper($join['type'])." JOIN {$join['table']} ON {$join['first']} = {$join['second']}";

        $this->command->line('SQL: '.$sql);
        $this->command->newLine();

        $this->command->table(
            ['Property', 'Value'],
            collect($join)->map(fn ($value, $key) => [
                $key,
                is_array($value) ? implode(', ', array_map(fn ($s) => is_array($s) ? ($s['column'] ?? '').' AS '.($s['alias'] ?? '') : (string) $s, $value)) : (is_bool($value) ? ($value ? 'true' : 'false') : $value),
            ])->toArray()
        );
        $this->command->newLine();
    }
}
