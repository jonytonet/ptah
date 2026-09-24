<?php

declare(strict_types=1);

namespace Ptah\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PtahLastError extends RunsPtahCommand
{
    protected string $description = "The last ERROR entry of the Laravel log, compact: exception and code, message, the SQL separated from it, where it was thrown, only the application's stack frames (vendor frames are counted, not printed) and the ptahErrorId shown on the 500 page.";

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'frames' => $schema->integer()->description('How many application frames to show (default 6).'),
        ];
    }

    public function handle(Request $request): Response
    {
        return $this->run('ptah:last-error', $request->get('frames') !== null ? ['--frames' => (int) $request->get('frames')] : []);
    }
}
