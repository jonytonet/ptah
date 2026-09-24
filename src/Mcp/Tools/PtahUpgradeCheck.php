<?php

declare(strict_types=1);

namespace Ptah\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PtahUpgradeCheck extends RunsPtahCommand
{
    protected string $description = 'After updating jonytonet/ptah: what THIS project must do — nested config keys missing from a published config/ptah.php, keys no longer read, published views and stubs that shadow the package, the user_preferences FK vs the configured identity, pending migrations.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        return $this->run('ptah:upgrade-check');
    }
}
