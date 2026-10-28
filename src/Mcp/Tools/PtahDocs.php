<?php

declare(strict_types=1);

namespace Ptah\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PtahDocs extends RunsPtahCommand
{
    protected string $description = 'Ptah documentation. With `topic`: the option reference for `php artisan ptah:config`, generated from the parser itself (column types, renderers, modifiers, filter/style/action/join syntax, the masks this app registered). With `search`: the best-matching sections of the docs shipped with the INSTALLED ptah version (e.g. "column permission", "bulk action handler", "forge-table slot"). Use instead of reading docs/*.md whole.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->description('column, filter, style, action, join or mask. Omit (and omit search) to list topics.'),
            'search' => $schema->string()->description('Free-text search over the docs, by section. English words match best; accents and case are ignored.'),
            'limit' => $schema->integer()->description('How many sections a search returns (default 3).'),
        ];
    }

    public function handle(Request $request): Response
    {
        if ($request->get('search')) {
            return $this->run('ptah:docs', array_filter([
                '--search' => (string) $request->get('search'),
                '--limit' => $request->get('limit') !== null ? (string) $request->get('limit') : null,
            ], fn ($v) => $v !== null));
        }

        return $this->run('ptah:docs', $request->get('topic') ? ['topic' => (string) $request->get('topic')] : []);
    }
}
