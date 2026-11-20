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

        /* While the next page is on its way the page being left is still on
           screen — and was still clickable. A click on it in that window (the
           row's Edit, a filter) started a request whose answer arrived AFTER
           the swap: Livewire then morphed a component that had already left
           the document, on a tree whose Alpine was torn down — "moreOpen is
           not defined", "Illegal invocation" (`open` resolving to
           window.open), and the modal the person asked for never opened
           (reported from production, 1.59.1). Two halves:

           1. The content of the page being left goes inert from the moment a
              navigation starts until the next page is in (or 10 s, if it
              never comes): a click there could only be lost. The sidebar and
              the top bar stay usable — choosing another screen is fine.
           2. On the swap, the messages still in flight for the components of
              the page being left are cancelled (Livewire.interceptMessage):
              whatever was pending — that click, a poll, a slow action — has
              no page to land on. Components carried over live (x-persist: the
              AI chat, the toasts) keep theirs. */
        var leaving = null, leavingTimer = null;
        function release() {
            clearTimeout(leavingTimer);
            if (leaving) {
                leaving.removeAttribute('inert');
                leaving.removeAttribute('aria-busy');
            }
            leaving = null;
        }
        document.addEventListener('livewire:navigate', function () {
            release();
            /* Before the content goes inert: an inert subtree drops the focus. */
            keepPersistedFocus();
            leaving = document.querySelector('main');
            if (!leaving) return;
            leaving.setAttribute('inert', '');
            leaving.setAttribute('aria-busy', 'true');
            leavingTimer = setTimeout(release, 10000);
        });

        var inFlight = [];
        function watchMessages() {
            if (spa.watching || !window.Livewire || typeof window.Livewire.interceptMessage !== 'function') return;
            spa.watching = true;
            window.Livewire.interceptMessage(function (m) {
                var entry = { message: m.message, cancel: m.cancel };
                inFlight.push(entry);
                m.onFinish(function () {
                    var at = inFlight.indexOf(entry);
                    if (at !== -1) inFlight.splice(at, 1);
                });
            });
        }
        watchMessages();
        document.addEventListener('livewire:init', watchMessages);

        function cancelMessagesOfThePageBeingLeft() {
            var pending = inFlight;
            inFlight = [];
            pending.forEach(function (entry) {
                try {
                    var el = entry.message && entry.message.component ? entry.message.component.el : null;
                    if (el && el.closest && el.closest('[x-persist]')) {
                        inFlight.push(entry);
                        return;
                    }
                    entry.cancel();
                } catch (e) {}
            });
        }

        /* Back/forward: Livewire snapshots the page it is LEAVING (its HTML, for
           a later forward/back) WITHOUT tearing Alpine down first — a link
           navigation does (cleanupAlpineElementsOnThePageThatArentInsideAPersistedElement).
           The snapshot then carries the elements Alpine rendered from
           <template x-for> / x-if, and restoring it initialises them OUTSIDE
           their loop ("option is not defined").

           So the same teardown runs here, on `livewire:navigating` (which fires
           before that snapshot): Alpine.destroyTree() over the body, skipping
           what Livewire carries over live — persisted blocks (x-persist) and teleport
           targets. It runs each directive's cleanup: the rendered copies are
           removed AND their effects and window/document listeners are released.

           1.59.0 only removed the copies from the DOM. Their effects stayed
           subscribed and, detached from their x-data, ran with an empty scope
           the next time that data changed: "moreOpen is not defined",
           "Illegal invocation" (`open` resolving to window.open) — and the
           row's Edit stopped opening the modal (reported from production,
           1.59.1). On a link navigation Livewire runs the very same teardown
           right after this one; a second pass over a destroyed tree is a no-op. */
        function tearDownAlpine() {
            var A = window.Alpine;
            if (!A || typeof A.destroyTree !== 'function' || typeof A.walk !== 'function' || !document.body) return;
            try {
                A.destroyTree(document.body, function (root, callback) {
                    A.walk(root, function (el, skip) {
                        if (el.hasAttribute && (el.hasAttribute('x-persist') || el.hasAttribute('data-teleport-target'))) {
                            skip();
                            return;
                        }
                        callback(el, skip);
                    });
                });
            } catch (e) {}
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
            keepPersistedScrolls();
            /* After reading the sidebar's state above: the teardown forgets it. */
            tearDownAlpine();
            cancelMessagesOfThePageBeingLeft();
        });

        /* A persisted block (the assistant, the toasts) is the same element on
           the next page — but Livewire takes it out of the document for the
           swap and puts it back, and an element taken out of the document
           forgets its scroll offset and its focus. The open chat jumped back
           to its first message, and a half-typed message lost the caret
           (1.59.2). What scrolled and what had the focus is noted here and
           given back once the block is in again. */
        /* The focus is noted when the navigation starts (`livewire:navigate`),
           before the content goes inert; the scrolls right before the swap
           (`livewire:navigating`), so a scroll made while the page was on its
           way counts. */
        function keepPersistedFocus() {
            spa.kept = { scrolls: [], focus: null };
            var active = document.activeElement;
            if (active && active !== document.body && active.closest && active.closest('[x-persist]')) {
                var focus = { el: active };
                try {
                    if (typeof active.selectionStart === 'number') {
                        focus.start = active.selectionStart;
                        focus.end = active.selectionEnd;
                    }
                } catch (e) {}
                spa.kept.focus = focus;
            }
        }

        function keepPersistedScrolls() {
            spa.kept = spa.kept || { scrolls: [], focus: null };
            spa.kept.scrolls = [];
            document.querySelectorAll('[x-persist]').forEach(function (block) {
                [block].concat(Array.prototype.slice.call(block.querySelectorAll('*'))).forEach(function (el) {
                    if (el.scrollTop || el.scrollLeft) spa.kept.scrolls.push({ el: el, top: el.scrollTop, left: el.scrollLeft });
                });
            });
        }

        function givePersistedStateBack() {
            var kept = spa.kept;
            if (!kept) return;
            kept.scrolls.forEach(function (s) {
                if (!s.el.isConnected) return;
                s.el.scrollTop = s.top;
                s.el.scrollLeft = s.left;
            });
            var f = kept.focus;
            if (f && f.el.isConnected && (document.activeElement === document.body || !document.activeElement)) {
                try {
                    f.el.focus({ preventScroll: true });
                    if (typeof f.start === 'number') f.el.setSelectionRange(f.start, f.end);
                } catch (e) {}
            }
        }

        document.addEventListener('livewire:navigated', function () {
            release();
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
            givePersistedStateBack();

            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    d.removeAttribute('data-ptah-swapping');
                    if (nav && spa.scroll !== null && nav.isConnected) nav.scrollTop = spa.scroll;
                    /* Again once the new page is laid out: a scroll given back
                       before layout can be clamped to a shorter height. */
                    givePersistedStateBack();
                    spa.kept = null;
                });
            });
        });
    })();
</script>
