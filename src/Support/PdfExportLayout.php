<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Page orientation and type size of the exported PDF.
 *
 * Until 1.43.2 the PDF was always A4 portrait: `exportConfig.orientation`
 * (editor → General, default landscape) was saved and never read, and a wide
 * listing lost its right-hand columns off the page.
 */
final class PdfExportLayout
{
    /** Above this many columns a portrait page cuts the table: landscape. */
    public const PORTRAIT_MAX_COLUMNS = 6;

    /**
     * `landscape` when configured so, or when the configured portrait (or no
     * choice at all) cannot hold the columns.
     */
    public static function orientation(mixed $configured, int $columns): string
    {
        if ($configured === 'landscape') {
            return 'landscape';
        }

        return $columns > self::PORTRAIT_MAX_COLUMNS ? 'landscape' : 'portrait';
    }

    /** Body font size (px) — smaller as the columns grow, so they fit the width. */
    public static function fontSize(int $columns): int
    {
        return match (true) {
            $columns > 16 => 6,
            $columns > 12 => 7,
            $columns > 8 => 8,
            default => 9,
        };
    }
}
