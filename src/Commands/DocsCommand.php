<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Ptah\Support\CliReference;
use Ptah\Support\DocsSearch;

/**
 * `ptah:docs <topic>` — the `ptah:config` vocabulary in a few hundred tokens.
 *
 * Built for whoever is configuring a screen, and very often that is an agent:
 * answering "what is the option for X?" from `docs/BaseCrud.md` meant reading
 * ~29k tokens to find one line. The answer here comes from the constants the
 * parser itself uses (see CliReference), so it cannot go stale.
 *
 * Uso:
 *   php artisan ptah:docs            # topics
 *   php artisan ptah:docs column
 *   php artisan ptah:docs column --json
 *   php artisan ptah:docs --search="permissao por coluna"   # 1.52.0: the docs, by section
 */
class DocsCommand extends Command
{
    protected $signature = 'ptah:docs
        {topic? : column, filter, style, action, join or mask}
        {--search= : Search the shipped docs (docs/*.md) and print the best sections}
        {--limit=3 : How many sections --search prints}
        {--json : Machine-readable output}';

    protected $description = 'Show the ptah:config option reference for a topic, straight from the parser';

    public function handle(): int
    {
        $query = trim((string) ($this->option('search') ?? ''));
        if ($query !== '') {
            return $this->search($query);
        }

        $name = $this->argument('topic');

        if ($name === null) {
            $this->line('topics: '.implode(', ', CliReference::topics()));
            $this->line('usage: php artisan ptah:docs <topic> [--json]');

            return self::SUCCESS;
        }

        $topic = CliReference::topic((string) $name);

        if ($topic === null) {
            $this->components->error("Unknown topic '{$name}'. Topics: ".implode(', ', CliReference::topics()));

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($topic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line(CliReference::render((string) $name, $topic));

        return self::SUCCESS;
    }

    private function search(string $query): int
    {
        $hits = DocsSearch::search($query, max(1, (int) $this->option('limit')));

        if ($this->option('json')) {
            $this->line((string) json_encode($hits, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($hits === []) {
            $this->line("No section of docs/*.md matches '{$query}'. Try fewer or other words (accents and case are ignored).");

            return self::SUCCESS;
        }

        foreach ($hits as $hit) {
            $this->line("── {$hit['file']} § {$hit['heading']}");
            $this->line($hit['body']);
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
