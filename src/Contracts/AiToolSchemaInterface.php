<?php

declare(strict_types=1);

namespace Ptah\Contracts;

/**
 * Optional companion to AiToolInterface: the tool's schema, without the tool.
 *
 * `AiToolInterface` declares `name()`, `description()` and `parameters()` as
 * instance methods, so describing a tool to the model means constructing it.
 * That is fine for two tools and expensive for twenty-six, because every turn
 * sends the full list of schemas and only ever calls one or two of them.
 *
 * A tool that also implements this interface is described from the static
 * method and constructed only when the model actually calls it. Everything else
 * — registration, execution, the arguments contract — is unchanged, and a tool
 * that does not implement it keeps working exactly as before: the registry
 * falls back to constructing it to read its schema.
 *
 *   final class ListOpenOrdersTool implements AiToolInterface, AiToolSchemaInterface
 *   {
 *       public function __construct(private readonly OrderRepositoryContract $orders) {}
 *
 *       public static function toolSchema(): array
 *       {
 *           return [
 *               'name' => 'list_open_orders',
 *               'description' => 'Lists orders that are still open.',
 *               'parameters' => [
 *                   'type' => 'object',
 *                   'properties' => [
 *                       'limit' => ['type' => 'integer', 'description' => 'Max rows'],
 *                   ],
 *               ],
 *           ];
 *       }
 *
 *       // name() / description() / parameters() can just return the static parts.
 *   }
 *
 * The static schema must agree with the instance methods — the model is told one
 * thing and calls another otherwise. `AiToolRegistry` does not reconcile them,
 * because doing so would mean constructing the tool, which is the cost this
 * interface exists to avoid.
 */
interface AiToolSchemaInterface
{
    /**
     * The tool's name, description and JSON-Schema parameters.
     *
     * @return array{name: string, description: string, parameters?: array<string, mixed>}
     */
    public static function toolSchema(): array;
}
