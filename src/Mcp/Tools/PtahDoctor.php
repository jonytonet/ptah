<?php

declare(strict_types=1);

namespace Ptah\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PtahDoctor extends RunsPtahCommand
{
    protected string $description = 'Audit every saved BaseCrud config (crud_configs) for the silent failures: keys the runtime never reads (with "did you mean"), legacy sections (actions, filters, styles), unrenderable selects, hooks and bulk/searchdropdown handlers that never run, notification rules that reach nobody, permission collisions. Read-only — never runs --fix; tell the user to run `php artisan ptah:config:doctor --fix` when it says so.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        return $this->run('ptah:config:doctor');
    }
}
