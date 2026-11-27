{{--
    A floating list (1.63.1): the open list of a select / searchdropdown is
    placed with position:fixed under (or over) its field, so a modal body that
    scrolls no longer hides it. It is measured against its containing block,
    so it lands right even inside a transformed ancestor (the draggable
    forge-modal panel keeps a `translate`). Flips up when there is no room
    below; follows scroll, resize, its own size (results arriving) and a
    morph that rewrites its style attribute while open.

    Goes inside an x-init on the list element:
        x-init="@include('ptah::partials.float', ['open' => 'open'])"
    Params: open — the Alpine expression that says the list is open;
            up   — true to prefer opening upwards (colsSDStartList = top).
    Single quotes only: this lands inside a double-quoted attribute.
--}}
window.ptahFloat ??= { place(a, p, up) { const s = p.style; s.position = 'fixed'; s.top = '0px'; s.left = '0px'; s.right = 'auto'; s.bottom = 'auto'; s.margin = '0'; const o = p.getBoundingClientRect(), r = a.getBoundingClientRect(), h = p.offsetHeight, g = 4, below = window.innerHeight - r.bottom - g, above = r.top - g; const flip = up ? (above >= h || above > below) : (h > below && above > below); s.top = ((flip ? r.top - g - h : r.bottom + g) - o.top) + 'px'; s.left = (r.left - o.left) + 'px'; s.width = r.width + 'px'; }, bind(a, p, up) { const go = () => window.ptahFloat.place(a, p, up); go(); window.addEventListener('scroll', go, true); window.addEventListener('resize', go); const mo = new MutationObserver(() => { if (p.style.position !== 'fixed') go(); }); mo.observe(p, { attributes: true, attributeFilter: ['style'] }); const ro = window.ResizeObserver ? new ResizeObserver(go) : null; if (ro) ro.observe(p); return () => { mo.disconnect(); if (ro) ro.disconnect(); window.removeEventListener('scroll', go, true); window.removeEventListener('resize', go); }; } };
let _ptahFloatOff = null;
$watch('{{ $open ?? 'open' }}', (v) => { if (_ptahFloatOff) { _ptahFloatOff(); _ptahFloatOff = null; } if (v) $nextTick(() => { _ptahFloatOff = window.ptahFloat.bind($el.parentElement, $el, {{ ! empty($up) ? 'true' : 'false' }}); }); });
