<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Str;

/**
 * Full-text search over the package's own docs/*.md, by section.
 *
 * An agent (or the VS Code extension) asking "how do I gate a column by
 * permission?" should get the two sections that answer it — not the 1,400
 * lines of Permissions.md, and not a guess. Each markdown heading starts a
 * section; a section scores by how many of the query's words it holds, a hit
 * in the heading counting more than one in the body. Accents and case are
 * ignored, so "permissao" finds "permissão".
 *
 * The docs searched are the ones shipped with the INSTALLED version, so the
 * answer matches the code the host runs.
 */
final class DocsSearch
{
    private const HEADING_WEIGHT = 15;

    private const MAX_BODY_LINES = 40;

    /** Words that match everywhere and rank nothing (pt and en). */
    private const STOPWORDS = [
        'and', 'the', 'for', 'with', 'how', 'what', 'from', 'that', 'this', 'into', 'not', 'are', 'can', 'does',
        'por', 'para', 'com', 'que', 'uma', 'como', 'dos', 'das', 'nos', 'nas', 'sem', 'mais', 'qual', 'quando',
    ];

    /**
     * @return list<array{file: string, heading: string, score: int, body: string}>
     */
    public static function search(string $query, int $limit = 3, ?string $docsPath = null): array
    {
        $terms = self::terms($query);
        if ($terms === []) {
            return [];
        }

        $docsPath ??= dirname(__DIR__, 2).'/docs';
        $hits = [];

        foreach (glob($docsPath.'/*.md') ?: [] as $path) {
            foreach (self::sections((string) file_get_contents($path)) as [$heading, $body]) {
                $score = self::score($terms, $heading, $body);
                if ($score > 0) {
                    $hits[] = [
                        'file' => 'docs/'.basename($path),
                        'heading' => $heading,
                        'score' => $score,
                        'body' => self::trimBody($body),
                    ];
                }
            }
        }

        usort($hits, fn (array $a, array $b) => [$b['score'], $a['file'], $a['heading']] <=> [$a['score'], $b['file'], $b['heading']]);

        return array_slice($hits, 0, max(1, $limit));
    }

    /**
     * @return list<string>
     */
    private static function terms(string $query): array
    {
        $words = preg_split('/[^a-z0-9_.:-]+/', self::fold($query)) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn (string $w) => strlen($w) >= 3 && ! in_array($w, self::STOPWORDS, true)
        )));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function sections(string $markdown): array
    {
        $sections = [];
        $heading = '';
        $body = [];
        $inFence = false;

        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $inFence = ! $inFence;
            }
            if (! $inFence && preg_match('/^(#{1,4})\s+(.+)$/', $line, $m)) {
                if ($heading !== '' || trim(implode("\n", $body)) !== '') {
                    $sections[] = [$heading, implode("\n", $body)];
                }
                $heading = trim($m[2]);
                $body = [];

                continue;
            }
            $body[] = $line;
        }
        $sections[] = [$heading, implode("\n", $body)];

        return $sections;
    }

    /**
     * @param  list<string>  $terms
     */
    private static function score(array $terms, string $heading, string $body): int
    {
        $heading = self::fold($heading);
        $body = self::fold($body);
        $score = 0;
        $matched = 0;

        foreach ($terms as $term) {
            // Start of a word: "por" must not score inside "export".
            $pattern = '/(?<![a-z0-9_])'.preg_quote($term, '/').'/';
            $inHeading = (int) preg_match_all($pattern, $heading);
            $inBody = min((int) preg_match_all($pattern, $body), 6);
            if ($inHeading + $inBody > 0) {
                $matched++;
            }
            $score += $inHeading * self::HEADING_WEIGHT + $inBody;
        }

        // A section holding every word beats one repeating a single word.
        return $matched === 0 ? 0 : $score * $matched;
    }

    private static function trimBody(string $body): string
    {
        $lines = explode("\n", trim($body));
        if (count($lines) <= self::MAX_BODY_LINES) {
            return implode("\n", $lines);
        }

        return implode("\n", array_slice($lines, 0, self::MAX_BODY_LINES))."\n…";
    }

    private static function fold(string $text): string
    {
        return strtolower(Str::ascii($text));
    }
}
