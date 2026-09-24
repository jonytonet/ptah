<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * May this string go into an `href`?
 *
 * HTML escaping does NOT neutralise a scheme: `href="{{ $url }}"` with
 * `javascript:alert(1)` runs the script, because there is nothing in it to
 * escape. So a URL that reaches an `href` from data — a menu item, a link
 * renderer template, a row action — has to be checked for its SCHEME.
 *
 * The package had that check in two places, as a regex:
 *
 *     /^\s*(javascript|data|vbscript):/i
 *
 * and it was bypassable, because it does not read the URL the way the browser
 * does. The WHATWG URL parser strips leading and trailing C0 controls and
 * spaces, and removes ASCII tab and newline from ANYWHERE in the input — so
 * `java\tscript:alert(1)` and `\x01javascript:alert(1)` are both, to the
 * browser, `javascript:alert(1)`, and neither matches the regex. And a menu
 * item's URL had no check at all: any authenticated user could store one and
 * wait for a master to click it in the sidebar.
 *
 * This reads the URL the browser's way first, then allows a short list of
 * schemes rather than denying a short list — a denylist is what let `data:`
 * and friends through in other projects before anyone thought of them.
 */
final class SafeUrl
{
    /**
     * Schemes that navigate somewhere and cannot execute anything.
     */
    public const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Where an unsafe URL goes instead: nowhere.
     */
    public const FALLBACK = '#';

    public static function isSafe(?string $url): bool
    {
        if ($url === null) {
            return true;
        }

        $scheme = self::schemeOf(self::normalise($url));

        // Sem esquema e relativa — `/pedidos`, `pedidos`, `#aba`, `?f=1`,
        // `//cdn.exemplo.com` —, e relativa nao executa nada.
        return $scheme === null || in_array($scheme, self::ALLOWED_SCHEMES, true);
    }

    /**
     * The URL itself when safe, `#` otherwise.
     *
     * Returns the ORIGINAL string for a safe URL, not the normalised one: the
     * normalisation exists to read the scheme the way a browser would, not to
     * rewrite what the host stored.
     */
    public static function sanitize(?string $url, string $fallback = self::FALLBACK): string
    {
        if ($url === null || $url === '') {
            return $fallback;
        }

        return self::isSafe($url) ? $url : $fallback;
    }

    /**
     * The input as the WHATWG URL parser sees it before looking for a scheme.
     */
    private static function normalise(string $url): string
    {
        // Tab, LF e CR saem de QUALQUER posicao — e isso que transforma
        // `java\tscript:` em `javascript:` no navegador.
        $url = str_replace(["\t", "\n", "\r"], '', $url);

        // Controles C0 (0x00-0x1F) e espaco saem das pontas.
        return trim($url, "\x00..\x20");
    }

    private static function schemeOf(string $url): ?string
    {
        // Um esquema e letra seguida de letras, digitos, `+`, `-` ou `.`, e
        // termina no primeiro `:` — desde que nenhum `/`, `?` ou `#` venha
        // antes, senao o `:` e parte de um caminho ou de uma query.
        if (preg_match('/^([A-Za-z][A-Za-z0-9+.\-]*):/', $url, $m) !== 1) {
            return null;
        }

        return strtolower($m[1]);
    }
}
