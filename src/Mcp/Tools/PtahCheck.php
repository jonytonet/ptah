<?php

declare(strict_types=1);

namespace Ptah\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PtahCheck extends RunsPtahCommand
{
    protected string $description = 'Smoke-test BaseCrud screens: renders each configured screen through Livewire and checks its config against the model and table (columns not in the table, form fields outside $fillable, NOT NULL columns the form never fills, invalid relations/sort/filter columns). Read-only. Run after changing models, migrations or configs.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()->description('Only screens whose model matches (class or short name). Omit for all screens.'),
            'user_id' => $schema->integer()->description('Render as this user (screens behind permissions return 403 anonymously).'),
            'guard' => $schema->string()->description('Auth guard for user_id (default guard if omitted).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $args = [];
        if ($request->get('model')) {
            $args['model'] = (string) $request->get('model');
        }
        if ($request->get('user_id') !== null) {
            $args['--as'] = (string) $request->get('user_id');
        }
        if ($request->get('guard')) {
            $args['--guard'] = (string) $request->get('guard');
        }

        return $this->run('ptah:check', $args);
    }
}
