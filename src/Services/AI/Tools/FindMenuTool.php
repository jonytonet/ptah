<?php

declare(strict_types=1);

namespace Ptah\Services\AI\Tools;

use Ptah\Contracts\AiToolInterface;
use Ptah\Contracts\AiToolSchemaInterface;
use Ptah\Support\MenuResolver;

/**
 * Lets the assistant answer "where is X?".
 *
 * The most common question a person asks about a system they did not build is
 * where something lives, and in an ERP with a few hundred screens that question
 * is asked constantly. The answer is already in the application — the sidebar
 * renders it on every page — so the assistant should not have to guess it, and
 * an assistant that guesses navigation is worse than one that declines: the
 * person follows the invented path, finds nothing, and stops trusting the
 * answers that were right.
 *
 * Hence `MenuResolver`: this tool and the sidebar read the SAME menu, through
 * the same resolution chain. If the two could drift, they would.
 *
 * ── Two decisions worth knowing ───────────────────────────────────────────
 *
 * It implements AiToolSchemaInterface, so describing it to the model does not
 * construct it. That is not for this tool's own sake — it has no dependencies —
 * but because a built-in that skipped its own interface would leave that path
 * untested by the package's own use of it.
 *
 * And it answers only for an authenticated user. The chat can be opened to
 * guests (`ptah.ai_agent.allow_guests`), and a guest never sees the sidebar; the
 * whole internal structure of the application is not something to hand out to
 * an anonymous session just because the chat is reachable.
 */
final class FindMenuTool implements AiToolInterface, AiToolSchemaInterface
{
    /** Cap on a browse (no query). A menu can hold hundreds of entries. */
    private const BROWSE_LIMIT = 60;

    /** Cap on a search. More than this is not an answer, it is a list. */
    private const SEARCH_LIMIT = 8;

    public static function toolSchema(): array
    {
        return [
            'name' => 'find_menu',
            'description' => 'Finds where a screen or feature lives in this application\'s navigation menu. '
                .'Use it whenever the user asks where something is, how to reach a screen, or what screens exist. '
                .'Returns the trail to click, from the top level down, and the URL. '
                .'Call it with no query to see the menu.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Words to look for in the menu, e.g. "cotacao" or "cadastro cliente". '
                            .'Accents and case are ignored. Omit to list the menu.',
                    ],
                ],
            ],
        ];
    }

    public function name(): string
    {
        return 'find_menu';
    }

    public function description(): string
    {
        return (string) (self::toolSchema()['description'] ?? '');
    }

    public function parameters(): array
    {
        /** @var array{type: string, properties: array<string, array<string, mixed>>, required?: list<string>} $schema */
        $schema = self::toolSchema()['parameters'];

        return $schema;
    }

    public function execute(array $arguments): array
    {
        if (! auth()->id()) {
            return [
                'available' => false,
                'reason' => 'The navigation menu is only described for a signed-in user.',
            ];
        }

        $query = trim((string) ($arguments['query'] ?? ''));

        $links = $query === ''
            ? MenuResolver::flatLinks()
            : MenuResolver::search($query, self::SEARCH_LIMIT);

        $total = $query === '' ? count($links) : null;

        if ($query === '') {
            $links = array_slice($links, 0, self::BROWSE_LIMIT);
        }

        if ($links === []) {
            return [
                'query' => $query,
                'found' => 0,
                // Said out loud, because a model handed an empty list tends to
                // fill it in. The instruction is the point.
                'note' => 'No menu entry matches. Say so instead of guessing a path, and suggest other words.',
            ];
        }

        $results = array_map(static fn (array $l): array => [
            'screen' => $l['label'],
            // Root to leaf, which is the order the person has to click.
            'path' => $l['path'],
            'url' => $l['url'],
        ], $links);

        $out = [
            'query' => $query,
            'found' => count($results),
            'results' => $results,
        ];

        if ($total !== null && $total > count($results)) {
            $out['truncated'] = true;
            $out['total'] = $total;
            $out['note'] = 'The menu has more entries than shown. Ask for a narrower query.';
        }

        return $out;
    }
}
