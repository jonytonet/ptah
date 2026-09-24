<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Ptah\Support\CliReference;

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
 */
class DocsCommand extends Command
{
    protected $signature = 'ptah:docs
        {topic? : column, filter, style, action, join or mask}
        {--json : Machine-readable output}';

    protected $description = 'Show the ptah:config option reference for a topic, straight from the parser';

    public function handle(): int
    {
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
}
