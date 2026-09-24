<?php

declare(strict_types=1);

namespace Ptah\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PtahWhyEmpty extends RunsPtahCommand
{
    protected string $description = 'Explain why a BaseCrud screen lists no rows for a user: mounts the real screen as that user (saved preferences, active company) and prints the row count after each layer (global scopes, company, search, saved filters, date ranges), marking the one that emptied it, plus the final SQL. Also reports a failing listing query, which the screen shows as empty.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()->description('The screen model key as in crud_configs, e.g. Catalog/Product.')->required(),
            'user_id' => $schema->integer()->description('The user who sees the empty screen. Preferences and company are per user.'),
            'guard' => $schema->string()->description('Auth guard for user_id (default guard if omitted).'),
            'route' => $schema->string()->description('Screen path, for a route-specific config (optional).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $args = ['model' => (string) $request->get('model')];
        foreach (['user_id' => '--as', 'guard' => '--guard', 'route' => '--route'] as $param => $option) {
            if ($request->get($param) !== null && $request->get($param) !== '') {
                $args[$option] = (string) $request->get($param);
            }
        }

        return $this->run('ptah:why-empty', $args);
    }
}
