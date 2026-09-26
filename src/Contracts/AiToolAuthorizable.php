<?php

declare(strict_types=1);

namespace Ptah\Contracts;

/**
 * Optional companion to AiToolInterface: who may use the tool.
 *
 * Without it every registered tool is offered to the model for every chat
 * user. In a real ERP that meant an attendant asking "who owes us money?" and
 * getting the whole receivables ledger back (achado #14 do PetPlace): the tool
 * ran the query with no idea who was asking.
 *
 * A tool that implements this interface is offered to the model ONLY when the
 * user holds the permission — a tool that is offered and then refused invites
 * the model to try another one — and the permission is checked again when the
 * model calls it.
 *
 *   final class ListReceivablesTool implements AiToolInterface, AiToolAuthorizable
 *   {
 *       public static function permission(): ?array
 *       {
 *           return ['financial.receivables', 'read'];
 *       }
 *   }
 *
 * Static on purpose: the registry decides before constructing the tool (see
 * AiToolRegistry on lazy resolution).
 */
interface AiToolAuthorizable
{
    /**
     * [page object key, action] checked with `ptah_can()`; the action defaults
     * to `read`. Return null to declare the tool deliberately open to anyone
     * who can use the chat — `ptah:check` warns only about tools that say
     * nothing.
     *
     * @return array{0: string, 1?: string}|null
     */
    public static function permission(): ?array;
}
