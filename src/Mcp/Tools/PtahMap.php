<?php

declare(strict_types=1);

namespace Ptah\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PtahMap extends RunsPtahCommand
{
    protected string $description = 'Compact map of this ptah project: every Eloquent entity (table, typed fields, FK targets, relations), every configured BaseCrud screen (route, permission, column and filter counts), the database menu and the TODOs the generators left. Call this first in a session instead of opening models, migrations and configs.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        return $this->run('ptah:map');
    }
}
