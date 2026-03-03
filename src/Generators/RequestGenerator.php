<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Generates the FormRequests for the entity.
 *
 * Web mode: StoreRequest and UpdateRequest under Http/Requests/{Folder}/
 * API mode: CreateApiRequest and UpdateApiRequest under Http/Requests/API/{Folder}/
 */
class RequestGenerator extends AbstractGenerator
{
    public function generate(EntityContext $context): GeneratorResult
    {
        // This method is only called when exactly one mode is active.
        // In combined mode (withApi + withViews), ScaffoldCommand calls
        // generateStore/Update and generateCreateApi/UpdateApi directly.
        if ($context->withApi && ! $context->withViews) {
            $this->generateCreateApi($context);
            return $this->generateUpdateApi($context);
        }

        // Web-only
        $this->generateStore($context);
        return $this->generateUpdate($context);
    }

    // ── Modo Web ──────────────────────────────────────────────────────────

    public function generateStore(EntityContext $context): GeneratorResult
    {
        $path = $context->subPath(config('ptah.paths.requests')) . "/Store{$context->entity}Request.php";
        $ns   = $context->subNs($context->rootNamespace . 'Http\\Requests');

        return $this->writeFile(
            path: $path,
            stub: 'request.store',
            replacements: [
                'namespace' => $ns,
                'entity'    => $context->entity,
                'rules'     => $context->validationRulesStore(),
            ],
            force: $context->force,
            labelOverride: "Request [Store{$context->entity}]",
        );
    }

    public function generateUpdate(EntityContext $context): GeneratorResult
    {
        $path = $context->subPath(config('ptah.paths.requests')) . "/Update{$context->entity}Request.php";
        $ns   = $context->subNs($context->rootNamespace . 'Http\\Requests');

        return $this->writeFile(
            path: $path,
            stub: 'request.update',
            replacements: [
                'namespace' => $ns,
                'entity'    => $context->entity,
                'rules'     => $context->validationRulesUpdate(),
            ],
            force: $context->force,
            labelOverride: "Request [Update{$context->entity}]",
        );
    }

    // ── Modo API ──────────────────────────────────────────────────────────

    public function generateCreateApi(EntityContext $context): GeneratorResult
    {
        $basePath = config('ptah.paths.requests');
        $path     = $context->subPath("{$basePath}/API") . "/Create{$context->entity}ApiRequest.php";
        $ns       = $context->subNs($context->rootNamespace . 'Http\\Requests\\API');

        return $this->writeFile(
            path: $path,
            stub: 'request.create.api',
            replacements: [
                'namespace' => $ns,
                'entity'    => $context->entity,
                'rules'     => $context->validationRulesStore(),
            ],
            force: $context->force,
            labelOverride: "Request API [Create{$context->entity}ApiRequest]",
        );
    }

    public function generateUpdateApi(EntityContext $context): GeneratorResult
    {
        $basePath = config('ptah.paths.requests');
        $path     = $context->subPath("{$basePath}/API") . "/Update{$context->entity}ApiRequest.php";
        $ns       = $context->subNs($context->rootNamespace . 'Http\\Requests\\API');

        return $this->writeFile(
            path: $path,
            stub: 'request.update.api',
            replacements: [
                'namespace' => $ns,
                'entity'    => $context->entity,
                'rules'     => $context->validationRulesUpdate(),
            ],
            force: $context->force,
            labelOverride: "Request API [Update{$context->entity}ApiRequest]",
        );
    }

    protected function label(): string
    {
        return 'Request';
    }
}
