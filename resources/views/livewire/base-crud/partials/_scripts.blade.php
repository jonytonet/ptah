{{-- ── Drag-and-drop + Resize de colunas ────────────────────────────── --}}
@once
{{-- Todas as regras deste bloco (scrollbar-gutter, drag/resize, labels e inputs
     do painel de filtro, sticky header, hover de linha, botões de ação e a
     barra de loading) moveram para resources/css/ptah-components.css —
     tokenizadas onde havia match exato de --ptah-*, mantidas byte-idênticas
     onde não havia (Onda 4 Parte A). --}}

<div id="ptah-resize-indicator"></div>

<script>
(function () {
    if (window.__ptahColDragInit) return;
    window.__ptahColDragInit = true;

    /* ─── row navigation: respects ctrl/cmd-click and middle-click ───── */
    window.ptahRowNav = function (event, url) {
        // Middle-click (auxclick) or ctrl/cmd-click → open in a new tab.
        if (event.button === 1 || event.ctrlKey || event.metaKey) {
            window.open(url, '_blank');

            return;
        }
        // Plain auxclick with another button (e.g. right) → ignore.
        if (event.type === 'auxclick') return;
        window.location = url;
    };

    /* ─── arrastar para rolar a tabela na horizontal ──────────────────
       Uma tabela larga é difícil de rolar com mouse: a maioria não tem roda
       horizontal, e shift+roda quase ninguém conhece. Arrastar resolve, mas o
       wrapper já é território disputado — o <th> tem drag HTML5 para reordenar
       coluna, a alça tem mousedown para redimensionar, e a linha pode ter
       clique de navegação (ptahRowNav). Daí as quatro regras abaixo.

       1. Só mouse. `pointerType` filtra toque e caneta, onde a rolagem nativa
          é melhor do que qualquer coisa que este código faria — e onde
          sequestrar o gesto quebraria o scroll da página.

       2. Só fora dos elementos que já reagem a ponteiro. Começar um pan em
          cima do <th> roubaria o drag de reorder; em cima de um botão,
          checkbox ou input, roubaria o clique.

       3. Limiar de 5px antes de considerar que é um arraste. Abaixo disso o
          gesto segue sendo seleção de texto — copiar o valor de uma célula é
          coisa que as pessoas fazem, e suprimir seleção sempre teria tirado
          isso sem avisar.

       4. Depois de arrastar de verdade, o `click` que o navegador dispara no
          mouseup é engolido UMA vez. Sem isso, arrastar uma tabela com
          `configLinkLinha` navegaria para o registro no fim do gesto.

       O cursor de mãozinha só aparece quando a tabela realmente transborda —
       oferecer o affordance numa tabela que não rola seria mentir sobre o que
       o gesto faz. */
    const PAN_THRESHOLD = 5;

    function panIgnoresTarget(el) {
        return !!el.closest(
            'th, button, a, input, select, textarea, label, summary, ' +
            '[draggable="true"], [contenteditable="true"], [data-ptah-no-pan]'
        );
    }

    function markPannable(wrap) {
        /* scrollWidth arredonda para inteiro; a folga de 1px evita ligar o
           cursor por causa de meio pixel de borda. */
        const can = wrap.scrollWidth - wrap.clientWidth > 1;
        wrap.classList.toggle('ptah-is-pannable', can);
    }

    function initPan(wrap) {
        if (wrap.__ptahPan) return;
        wrap.__ptahPan = true;

        markPannable(wrap);

        if (window.ResizeObserver) {
            const ro = new ResizeObserver(() => markPannable(wrap));
            ro.observe(wrap);
        }

        let startX = 0, startScroll = 0, panning = false, armed = false;

        wrap.addEventListener('pointerdown', (e) => {
            if (e.pointerType !== 'mouse' || e.button !== 0) return;
            if (panIgnoresTarget(e.target)) return;
            if (wrap.scrollWidth - wrap.clientWidth <= 1) return;

            armed = true;
            panning = false;
            startX = e.clientX;
            startScroll = wrap.scrollLeft;
        });

        wrap.addEventListener('pointermove', (e) => {
            if (!armed) return;

            const dx = e.clientX - startX;

            if (!panning) {
                if (Math.abs(dx) < PAN_THRESHOLD) return;
                /* Cruzou o limiar: só agora isto é um arraste. */
                panning = true;
                wrap.classList.add('ptah-is-panning');
                /* setPointerCapture mantém os eventos vindo mesmo quando o
                   cursor sai do wrapper — sem isso o pan travava ao passar
                   sobre a coluna sticky de ações. */
                if (wrap.setPointerCapture) {
                    try { wrap.setPointerCapture(e.pointerId); } catch (_) {}
                }
                /* Limpa a seleção que os primeiros pixels possam ter criado. */
                const sel = window.getSelection && window.getSelection();
                if (sel && sel.removeAllRanges) sel.removeAllRanges();
            }

            e.preventDefault();
            wrap.scrollLeft = startScroll - dx;
        });

        function endPan(e) {
            if (!armed) return;
            armed = false;

            if (!panning) return;
            panning = false;
            wrap.classList.remove('ptah-is-panning');

            if (wrap.releasePointerCapture && e && e.pointerId !== undefined) {
                try { wrap.releasePointerCapture(e.pointerId); } catch (_) {}
            }

            /* Engole o clique deste gesto, uma vez, na fase de captura — antes
               que ele chegue ao @click da linha. */
            wrap.addEventListener('click', function swallow(ev) {
                ev.stopPropagation();
                ev.preventDefault();
                wrap.removeEventListener('click', swallow, true);
            }, true);
        }

        wrap.addEventListener('pointerup', endPan);
        wrap.addEventListener('pointercancel', endPan);
        /* Se o botão for solto fora da janela, o pointerup não chega. */
        window.addEventListener('blur', () => endPan(null));
    }

    function initAllPans() {
        document.querySelectorAll('.ptah-c-tbl_wrap').forEach(initPan);
    }

    document.addEventListener('DOMContentLoaded', initAllPans);
    /* Livewire troca o DOM da tabela a cada render (paginar, filtrar, ordenar);
       o hook reinstala o comportamento nos wrappers novos. `__ptahPan` evita
       ligar duas vezes no mesmo elemento. */
    document.addEventListener('livewire:navigated', initAllPans);
    if (window.Livewire && window.Livewire.hook) {
        window.Livewire.hook('morphed', initAllPans);
    }
    initAllPans();

    /* ─── estado global ─────────────────────────────────────── */
    let _draggedTh = null, _draggedIdx = null, _dragCrudId = null;
    let _resizeTh = null, _resizeStart = 0, _resizeStartW = 0, _resizeField = null, _resizeCrud = null;
    const _indicator = () => document.getElementById('ptah-resize-indicator');

    /* ─── helper: encontra o componente Livewire da tabela ───── */
    function findWire(crudId) {
        const wrap   = document.getElementById('ptah-table-wrap-' + crudId);
        const wireEl = wrap?.closest('[wire\\:id]');
        return wireEl ? Livewire.find(wireEl.getAttribute('wire:id')) : null;
    }

    /* ─── helper: colunas sortable de uma thead row ─────────── */
    function sortableThs(crudId) {
        const row = document.getElementById('ptah-thead-row-' + crudId);
        return row ? Array.from(row.querySelectorAll('th.ptah-sortable-col')) : [];
    }

    /* ═══════════════════════════════════════════════════════
       DRAG-AND-DROP DE COLUNAS
    ══════════════════════════════════════════════════════════ */
    window.ptahColDragStart = function (e, crudId) {
        // Não iniciar drag se vier do resize handle
        if (e.target.closest('.ptah-resize-handle')) {
            e.preventDefault(); return;
        }
        _draggedTh  = e.currentTarget.closest('th');
        _dragCrudId = crudId;
        const ths   = sortableThs(crudId);
        _draggedIdx = ths.indexOf(_draggedTh);

        _draggedTh.classList.add('ptah-dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(_draggedIdx));
    };

    window.ptahColDragOver = function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const targetTh = e.target.closest('th.ptah-sortable-col');
        if (!targetTh || targetTh === _draggedTh || !_dragCrudId) return;

        sortableThs(_dragCrudId).forEach(th => th.classList.remove('ptah-drag-over'));
        targetTh.classList.add('ptah-drag-over');
    };

    window.ptahColDragDrop = function (e, crudId) {
        e.stopPropagation();
        const targetTh = e.target.closest('th.ptah-sortable-col');
        if (!targetTh || targetTh === _draggedTh) return;

        const ths      = sortableThs(crudId);
        const currOrder = ths.map(th => th.dataset.column);
        const toIdx     = ths.indexOf(targetTh);

        // Reordenar o array
        const fromField = currOrder.splice(_draggedIdx, 1)[0];
        currOrder.splice(toIdx, 0, fromField);

        // Mover DOM imediatamente para feedback instantâneo (Livewire re-render depois)
        const parent = targetTh.parentNode;
        if (toIdx < _draggedIdx) {
            parent.insertBefore(_draggedTh, targetTh);
        } else {
            parent.insertBefore(_draggedTh, targetTh.nextSibling);
        }

        // Persistir via Livewire
        const wire = findWire(crudId);
        if (wire) wire.call('reorderColumns', currOrder);
    };

    window.ptahColDragEnd = function (e) {
        if (_draggedTh) _draggedTh.classList.remove('ptah-dragging');
        if (_dragCrudId) sortableThs(_dragCrudId).forEach(th => th.classList.remove('ptah-drag-over'));
        _draggedTh = null; _draggedIdx = null; _dragCrudId = null;
    };

    /* ═══════════════════════════════════════════════════════
       RESIZE DE COLUNAS
    ══════════════════════════════════════════════════════════ */
    window.ptahResizeStart = function (e, field, crudId) {
        e.preventDefault(); e.stopPropagation();
        _resizeTh      = e.target.closest('th');
        _resizeField   = field;
        _resizeCrud    = crudId;
        _resizeStart   = e.pageX;
        _resizeStartW  = _resizeTh.offsetWidth;

        const ind = _indicator();
        if (ind) { ind.style.left = e.pageX + 'px'; ind.classList.add('active'); }
        document.body.style.cursor     = 'col-resize';
        document.body.style.userSelect = 'none';
    };

    document.addEventListener('mousemove', function (e) {
        if (!_resizeTh) return;
        const newW = Math.max(60, _resizeStartW + (e.pageX - _resizeStart));
        _resizeTh.style.width    = newW + 'px';
        _resizeTh.style.minWidth = newW + 'px';
        const ind = _indicator();
        if (ind) ind.style.left = e.pageX + 'px';
    });

    document.addEventListener('mouseup', function (e) {
        if (!_resizeTh) return;
        const finalW = _resizeTh.offsetWidth;

        const ind = _indicator();
        if (ind) ind.classList.remove('active');
        document.body.style.cursor     = '';
        document.body.style.userSelect = '';

        const wire = findWire(_resizeCrud);
        if (wire && _resizeField) wire.call('saveColumnWidth', _resizeField, finalW);

        _resizeTh = null; _resizeField = null; _resizeCrud = null;
    });

    /* ═══════════════════════════════════════════════════════
       DOUBLE-CLICK AUTO-FIT COLUNA
    ══════════════════════════════════════════════════════════ */
    document.addEventListener('dblclick', function (e) {
        const handle = e.target.closest('.ptah-resize-handle');
        if (!handle) return;
        e.preventDefault(); e.stopPropagation();
        const th = handle.closest('th');
        if (!th) return;
        const crudId  = th.closest('tr')?.id?.replace('ptah-thead-row-', '');
        const field   = th.dataset?.column;
        if (!th || !crudId || !field) return;

        // Reset: limpa width inline e salva null no servidor
        th.style.width    = '';
        th.style.minWidth = '60px';
        const wire = findWire(crudId);
        if (wire) wire.call('saveColumnWidth', field, null);
    });

})();
</script>

{{-- ═══════════════════════════════════════════════════════
     HIGHLIGHT DE BUSCA NAS CÉLULAS
    ══════════════════════════════════════════════════════════ --}}
<script>
(function () {
    if (window.__ptahHighlightInit) return;
    window.__ptahHighlightInit = true;

    function escapeRegex(s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function ptahHighlight(wrap, term) {
        // Limpa marks anteriores
        wrap.querySelectorAll('mark.ptah-hl').forEach(m => {
            const parent = m.parentNode;
            parent.replaceChild(document.createTextNode(m.textContent), m);
            parent.normalize();
        });
        if (!term || term.length < 2) return;
        const regex = new RegExp('(' + escapeRegex(term) + ')', 'gi');
        wrap.querySelectorAll('td').forEach(td => {
            // Só atua em nós de texto diretos e simples (evita quebrar HTML complexo)
            Array.from(td.childNodes).forEach(node => {
                if (node.nodeType !== Node.TEXT_NODE) return;
                const text = node.nodeValue;
                if (!regex.test(text)) return;
                regex.lastIndex = 0;
                const frag = document.createDocumentFragment();
                let last = 0, match;
                while ((match = regex.exec(text)) !== null) {
                    frag.appendChild(document.createTextNode(text.slice(last, match.index)));
                    const mark = document.createElement('mark');
                    mark.className = 'ptah-hl';
                    mark.textContent = match[0];
                    frag.appendChild(mark);
                    last = match.index + match[0].length;
                }
                frag.appendChild(document.createTextNode(text.slice(last)));
                td.replaceChild(frag, node);
            });
        });
    }

    function runHighlight() {
        document.querySelectorAll('[id^="ptah-table-wrap-"]').forEach(wrap => {
            const wireEl = wrap.closest('[wire\\:id]');
            if (!wireEl) return;
            // Lê o termo direto do input de busca no DOM (mais confiável que wire.search)
            const searchEl = wireEl.querySelector('[wire\\:model\\.live\\.debounce\\.400ms="search"]');
            const term = (searchEl?.value ?? '').trim();
            ptahHighlight(wrap, term);
        });
    }

    document.addEventListener('livewire:navigated', runHighlight);

    // Livewire 4: hook no commit para rodar após cada atualização de componente
    document.addEventListener('livewire:initialized', () => {
        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => { queueMicrotask(runHighlight); });
        });
    });
})();
</script>

{{-- ═══════════════════════════════════════════════════════
     EXPORT LISTENERS (Excel/PDF Download)
    ══════════════════════════════════════════════════════════ --}}
<script>
document.addEventListener('livewire:init', () => {
    if (window.__ptahExportInit) return;
    window.__ptahExportInit = true;

    // Listener para exportação (Excel/PDF) — abre o download do snapshot em cache
    // gerado pelo componente (token; o servidor resolve o model e os ids filtrados).
    Livewire.on('ptah:export-download', (event) => {
        const data = Array.isArray(event) ? event[0] : event;
        if (data && data.url) {
            window.open(data.url, '_blank');
        }
    });

    // Listener para a tela de impressão (abre o snapshot em cache em nova aba)
    Livewire.on('ptah:open-print', (event) => {
        const data = Array.isArray(event) ? event[0] : event;
        if (data && data.url) {
            window.open(data.url, '_blank');
        }
    });
});
</script>
@endonce

