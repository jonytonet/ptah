<?php

declare(strict_types=1);

namespace Ptah\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class PtahScreen extends RunsPtahCommand
{
    protected string $description = 'One BaseCrud screen summarized in ~20 lines: columns (type, label, form/required/filter/hidden, renderer, mask, relation, searchdropdown, rules, column permission), filters, row actions, styles, joins, hooks, permission and settings. Use before editing a screen instead of reading its JSON config.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'model' => $schema->string()->description('Model key as in crud_configs (Catalog/Product), FQCN, or a unique short name (Product).')->required(),
            'route' => $schema->string()->description('Only the route-specific config for this path (optional).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $args = ['model' => (string) $request->get('model')];
        if ($request->get('route')) {
            $args['--route'] = (string) $request->get('route');
        }

        return $this->run('ptah:screen', $args);
    }
}
