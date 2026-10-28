<?php

declare(strict_types=1);

namespace Ptah\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PtahValidateConfig extends RunsPtahCommand
{
    protected string $description = 'Check a BaseCrud config JSON BEFORE saving it — the same audit ptah:config:doctor runs on saved configs, on one config that is not saved (nothing is written). Catches keys the runtime never reads (e.g. a top-level "actions"/"filters"/"rowStyles", a misspelled cols* key), malformed sections and handlers that would never run. Call it on any config you wrote by hand; exit 1 in the text means errors.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'config' => $schema->string()->description('The config as a JSON string: the bare config ({"cols": [...], ...}) or an export envelope ({"model", "route", "config"}).')->required(),
            'model' => $schema->string()->description('The model it is for (class or runtime key, e.g. "Product" or "Catalog/Product"). Optional for an export envelope.'),
            'route' => $schema->string()->description('Route of a route-specific config.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $args = ['--config' => (string) $request->get('config')];
        if ($request->get('model')) {
            $args['--model'] = (string) $request->get('model');
        }
        if ($request->get('route')) {
            $args['--route'] = (string) $request->get('route');
        }

        return $this->run('ptah:config:doctor', $args);
    }
}
