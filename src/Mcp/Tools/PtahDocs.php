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
    protected string $description = 'Option reference for `php artisan ptah:config`, generated from the parser itself: column types, renderers, modifiers, filter/style/action/join syntax, and the masks this app registered. Use instead of reading docs/BaseCrud.md or docs/Configuration.md.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->description('column, filter, style, action, join or mask. Omit to list topics.'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->run('ptah:docs', $request->get('topic') ? ['topic' => (string) $request->get('topic')] : []);
    }
}
