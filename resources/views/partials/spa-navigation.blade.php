{{--
    ptah::partials.spa-navigation — only rendered when ptah.forge.spa_navigation
    is on (see Ptah\Support\SpaNavigation). First child of <body> in
    forge-dashboard-layout, on purpose:

    With Livewire's navigate the new page's <body> replaces the old one and every
    <script> in it runs again (unless marked data-navigate-once), while
    `window` and `document` survive. So this script splits in two:

      1. The part ABOVE the once-guard runs on EVERY visit, synchronously, as
         the new body is inserted — before any other body script, before
         Alpine starts on the new page. The swap rewrites <html>'s attributes
         with the server's, and the server never renders the dark class (the
         visitor's localStorage may know better than the server), so the
         class is re-applied here, before anything can read a style. It also
         marks the swap (`data-ptah-swapping`), which switches CSS transitions
         off until the new page has settled: without it the sidebar width and
         the content margin — computed by Alpine — would animate from their
         pre-Alpine value on every navigation (the frame "breathing").

      2. The part BELOW runs once per document (window.ptahSpa guard): the
         listeners that carry the sidebar's state across the swap and the
         build check. Registered once, never piled up.

    The sidebar is RE-RENDERED on every page (not @persist): it lives inside
    the layout's root x-data (sidebarCollapsed, sidebarOpen), and a persisted
    element keeps the Alpine scope of the OLD root — the navbar's collapse
    button would then drive a root the sidebar no longer listens to. What a
    re-render would lose is restored instead: the nav's scroll position, the
    groups the person opened, and the hover-expanded state.

    ATENÇÃO: aspas simples no JS (hábito do pacote, ver LayoutXDataQuotingTest).
--}}
<style>
    html[data-ptah-swapping] *,
    html[data-ptah-swapping] *::before,
    html[data-ptah-swapping] *::after { transition: none !important; }
    /* Livewire's progress bar in the accent token (it defines the variable on
       :root; html:root wins on specificity). */
    html:root { --livewire-progress-bar-color: var(--ptah-primary, var(--color-primary)); }
    @media (prefers-reduced-motion: reduce) {
        #nprogress .bar { transition: none !important; }
        #nprogress .peg { display: none; }
    }
</style>
<script>
    (function () {
        var d = document.documentElement;
        try {
            if (localStorage.getItem('ptah_dark_mode') === 'true') {
                d.classList.add('ptah-dark', 'dark');
            }
        } catch (e) {}
        d.setAttribute('data-ptah-swapping', '');

        if (window.ptahSpa) return;

        var buildMeta = document.querySelector('meta[name=ptah-build]');
        var spa = window.ptahSpa = {
            hovered: false,
            open: [],
            scroll: null,
            build: buildMeta ? buildMeta.getAttribute('content') : null
        };

        function sidebarNav() {
            var aside = document.querySelector('aside.ptah-sidebar');
            return aside ? aside.querySelector('nav') : null;
        }

        /* The links the layout marks (data-ptah-navigate, decided server-side by
           Ptah\Support\SpaNavigation) go through Livewire.navigate(). Not
           Livewire's own link attribute: its prefetch cache has a race in Livewire 4.3.1 (a
           stale 30s timer deletes a newer in-flight prefetch of the same URL ->
           "Cannot set properties of undefined (setting 'html')", and a click
           waiting on it never lands) — see SpaNavigation::ATTRIBUTE. A modified
           or non-primary click keeps the browser's behaviour (new tab...). */
        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            var a = e.target && e.target.closest ? e.target.closest('a[data-ptah-navigate]') : null;
            if (!a || !a.href || !document.documentElement.hasAttribute('data-ptah-spa')) return;
            if (!window.Livewire || typeof window.Livewire.navigate !== 'function') return;
            e.preventDefault();
            window.Livewire.navigate(a.href);
        });

        /* Back/forward: Livewire snapshots the page it is LEAVING (its HTML, for
           a later forward/back) without first tearing down Alpine — unlike a
           link navigation. The snapshot then carries the elements Alpine
           rendered from <template x-for> / x-if (a select's options, a list),
           and restoring it initialises them OUTSIDE their loop: "option is not
           defined". `livewire:navigating` fires before that snapshot, so the
           rendered copies are dropped here; the templates render them again
           when the page is restored. Persisted blocks keep theirs (they are
           carried over live, not from the snapshot). */
        function dropAlpineRenderedCopies() {
            document.querySelectorAll('template[x-for], template[x-if]').forEach(function (tpl) {
                if (tpl.closest('[x-persist]')) return;
                /* Alpine 3 keeps them in a Map (key -> element). */
                if (tpl._x_lookup && typeof tpl._x_lookup.forEach === 'function') {
                    tpl._x_lookup.forEach(function (n) {
                        if (n && n.parentNode) n.parentNode.removeChild(n);
                    });
                }
                if (tpl._x_currentIfEl && tpl._x_currentIfEl.parentNode) {
                    tpl._x_currentIfEl.parentNode.removeChild(tpl._x_currentIfEl);
                }
            });
        }

        document.addEventListener('livewire:navigating', function () {
            var aside = document.querySelector('aside.ptah-sidebar');
            var nav = sidebarNav();
            spa.hovered = !!(aside && aside.matches(':hover'));
            spa.scroll = nav ? nav.scrollTop : null;
            spa.open = [];
            if (aside && window.Alpine) {
                aside.querySelectorAll('[data-ptah-group]').forEach(function (li) {
                    try {
                        if (window.Alpine.$data(li).open) spa.open.push(li.getAttribute('data-ptah-group'));
                    } catch (e) {}
                });
            }
            dropAlpineRenderedCopies();
        });

        document.addEventListener('livewire:navigated', function () {
            /* Two cases end in a real reload instead of a swapped page:
               - a deploy changed the build. Vite's hashed file names change the
                 PATH, not the query string, so Livewire's own
                 data-navigate-track check does not fire; old JS would run
                 against new HTML.
               - the page that arrived is not this layout with the mode on (no
                 meta): the login screen after the session expired, a host page
                 with its own layout and scripts. Those assume a document load. */
            var meta = document.querySelector('meta[name=ptah-build]');
            if (!meta || meta.getAttribute('content') !== spa.build) {
                window.location.reload();
                return;
            }

            var nav = sidebarNav();
            if (nav && spa.scroll !== null) nav.scrollTop = spa.scroll;

            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    d.removeAttribute('data-ptah-swapping');
                    if (nav && spa.scroll !== null && nav.isConnected) nav.scrollTop = spa.scroll;
                });
            });
        });
    })();
</script>
