{{--
    Shared shell for every ptah error page (403, 404, 419, 429, 500, 503).

    ── The one design constraint that shapes everything here ──────────────
    An error page has to survive the failure that produced it. A 500 may be
    rendering because the database is gone, the cache driver is unreachable or
    the Vite build was never run — so this page must not depend on any of them.
    But the author's complaint was the opposite: the old 403 ignored the user's
    theme entirely.

    Both are satisfied by chaining fallbacks instead of choosing a side:

        background: var(--ptah-canvas, var(--err-canvas));

    When `ptah-components.css` is loaded, `--ptah-canvas` exists and wins, so
    the page follows all six appearance axes like any other screen. When it is
    not loaded — no build, broken asset pipeline — the token is undefined and
    the browser falls through to `--err-canvas`, declared right here and given
    a dark variant through `prefers-color-scheme`. Nothing external is
    required for the page to be readable.

    For the same reason this file carries its own CSS inline and uses the
    system font stack: a webfont request is one more thing that can fail while
    the site is already failing.

    ── Sections a page fills ──────────────────────────────────────────────
    @section('code')     the HTTP status, shown as the largest element
    @section('title')    one human sentence — never "Forbidden"
    @section('body')     what happened and what to do about it
    @section('actions')  optional; the shell renders Back + Home by default
    @section('glyph')    optional inline SVG, drawn in the accent tint
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') · @yield('title') — {{ config('app.name', 'Ptah') }}</title>

    {{-- The package stylesheet is loaded when the host has a build, purely so
         the tokens resolve and the page picks up the chosen theme. It is never
         required: every rule below has a literal fallback. No JS, no CDN, no
         webfont — this page must render when the rest of the app cannot. --}}
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif

    <style>
        :root {
            --err-canvas: #f7f7f4;
            --err-surface: #ffffff;
            --err-line: #e0e1dc;
            --err-ink: #101f3b;
            --err-ink-soft: #43536e;
            --err-ink-faint: #6e7c93;
            --err-accent: #14305f;
            --err-on-accent: #ffffff;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --err-canvas: #071531;
                --err-surface: #0a1b38;
                --err-line: #1b3358;
                --err-ink: #eaeef6;
                --err-ink-soft: #a8b6ce;
                --err-ink-faint: #74849f;
                --err-accent: #e3be6e;
                --err-on-accent: #071531;
            }
        }

        * { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--ptah-canvas, var(--err-canvas));
            color: var(--ptah-text, var(--err-ink));
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto,
                         "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .err {
            width: 100%;
            max-width: 34rem;
            text-align: center;
        }

        .err-glyph {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 1.25rem;
            color: var(--ptah-primary, var(--err-accent));
        }
        .err-glyph svg { width: 100%; height: 100%; }

        /* The status code is the page's headline, not decoration: it is the one
           piece a user can hand to support. Large, tabular, and never dimmed
           into unreadability — it sits at --ptah-text-secondary, not faint. */
        .err-code {
            font-size: clamp(3.5rem, 14vw, 5.5rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.03em;
            font-variant-numeric: tabular-nums;
            color: var(--ptah-text-secondary, var(--err-ink-soft));
            margin: 0 0 .75rem;
        }

        .err-title {
            font-size: clamp(1.25rem, 4vw, 1.6rem);
            font-weight: 700;
            line-height: 1.25;
            text-wrap: balance;
            color: var(--ptah-text-strong, var(--err-ink));
            margin: 0 0 .85rem;
        }

        .err-body {
            font-size: .95rem;
            color: var(--ptah-text-secondary, var(--err-ink-soft));
            margin: 0 auto;
            max-width: 42ch;
            text-wrap: pretty;
        }

        .err-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .err-btn {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .6rem 1.15rem;
            border-radius: 4px;
            border: 1px solid var(--ptah-line-strong, var(--err-line));
            background: var(--ptah-surface, var(--err-surface));
            color: var(--ptah-text, var(--err-ink));
            font-size: .9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: border-color .14s, transform .14s;
        }
        .err-btn:hover { border-color: var(--ptah-primary, var(--err-accent)); transform: translateY(-1px); }
        .err-btn:focus-visible {
            outline: 2px solid var(--ptah-primary, var(--err-accent));
            outline-offset: 2px;
        }

        .err-btn--primary {
            background: var(--ptah-primary, var(--err-accent));
            border-color: var(--ptah-primary, var(--err-accent));
            color: var(--ptah-text-on-accent, var(--err-on-accent));
        }
        .err-btn--primary:hover { border-color: var(--ptah-primary, var(--err-accent)); }

        .err-btn svg { width: 1rem; height: 1rem; }

        /* The reference line: only rendered when there is something a person
           can actually quote to support. An empty "Reference:" is noise. */
        .err-ref {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--ptah-line, var(--err-line));
            font-size: .75rem;
            color: var(--ptah-text-faint, var(--err-ink-faint));
        }
        .err-ref code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: .78rem;
            color: var(--ptah-text-secondary, var(--err-ink-soft));
            user-select: all;
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
    <main class="err">
        @hasSection('glyph')
            <div class="err-glyph" aria-hidden="true">@yield('glyph')</div>
        @endif

        <p class="err-code">@yield('code')</p>
        <h1 class="err-title">@yield('title')</h1>
        <p class="err-body">@yield('body')</p>

        <div class="err-actions">
            @hasSection('actions')
                @yield('actions')
            @else
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : config('ptah.auth.home', '/') }}" class="err-btn">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    {{ __('ptah::ui.error_btn_back') }}
                </a>
                <a href="{{ config('ptah.auth.home', '/') }}" class="err-btn err-btn--primary">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    {{ __('ptah::ui.error_btn_home') }}
                </a>
            @endif
        </div>

        @yield('reference')
    </main>
</body>
</html>
