{{--
    ptah::partials.appearance-boot

    Anti-FOUC boot script: resolves dark/light and paints `.ptah-dark`/`.dark`
    on <html> BEFORE first paint. Blocking + synchronous (no `defer`, no
    `DOMContentLoaded`), included inside <head> so nothing after it ever
    flashes the wrong theme on F5 / navigation.

    Shared by forge-dashboard-layout.blade.php and layouts/forge-auth.blade.php
    — do not fork this script, extend it here so both stay identical.

    Expects a `$theme` variable in scope: 'dark' | 'light' | null.
      - 'dark' / 'light'  → an authoritative server opinion (database for an
        authenticated user, or the `ptah_appearance` cookie's `mode` for the
        auth screens / a visitor). Written straight to localStorage so the
        two stay in sync.
      - null              → no server opinion (visitor who never toggled, or
        an authenticated user who never touched the navbar toggle). Falls
        back to localStorage, i.e. the client remembers its own choice.

    Precedence, resolved by the caller before this partial ever runs (see the
    `$theme` / `$ptahAppearance` assignment in each layout):
      caller's `:theme` prop > database (authenticated) > `ptah_appearance`
      cookie > localStorage > light (default).

    ATENÇÃO: nunca use aspas duplas dentro deste script — ele é incluído
    dentro de <head>, fora de qualquer atributo HTML, então isso por si só não
    aciona o LayoutXDataQuotingTest, mas o hábito é o mesmo em todo o pacote:
    aspas simples em JS.
--}}
<script>
    (function () {
        try {
            var serverTheme = @js($theme);
            var isDark;
            if (serverTheme === 'dark' || serverTheme === 'light') {
                isDark = serverTheme === 'dark';
                localStorage.setItem('ptah_dark_mode', isDark);
            } else {
                var saved = localStorage.getItem('ptah_dark_mode');
                isDark = saved === 'true';
            }
            if (isDark) {
                document.documentElement.classList.add('ptah-dark', 'dark');
            }
        } catch (e) {}
    })();
</script>
