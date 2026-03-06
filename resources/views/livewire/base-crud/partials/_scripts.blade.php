{{-- ── Drag-and-drop + Resize de colunas ────────────────────────────── --}}
@once
<style>
    /* Drag feedback */
    .ptah-sortable-col.ptah-dragging   { opacity: .45; }
    .ptah-sortable-col.ptah-drag-over  { outline: 2px solid #6366f1; outline-offset: -2px; }
    .ptah-drag-grip                    { touch-action: none; }

    /* Resize indicator */
    #ptah-resize-indicator {
        position: fixed; top: 0; bottom: 0; width: 2px;
        background: #6366f1; z-index: 9999; pointer-events: none; display: none;
    }
    #ptah-resize-indicator.active { display: block; }

    /* ── Base CRUD global polish ───────────────────────────────────── */
    /* Filter panel field labels and inputs */
    .ptah-base-crud .p-4 label,
    .ptah-base-crud .space-y-4 label:not(.flex) {
        display: block;
        margin-bottom: .375rem;
        font-size: .6875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #64748B;
    }

    /* All filter inputs/selects: slate borders + indigo focus */
    .ptah-base-crud .p-4 input:not([type="checkbox"]),
    .ptah-base-crud .p-4 select {
        border-color: #E2E8F0;
        background-color: #FFFFFF;
        transition: border-color .15s, box-shadow .15s;
    }
    .ptah-base-crud .p-4 input:not([type="checkbox"]):focus,
    .ptah-base-crud .p-4 select:focus {
        border-color: #818CF8;
        box-shadow: 0 0 0 3px rgba(129, 140, 248, .15);
        outline: none;
    }

    /* ── Dark Mode (ptah-dark) ────────────────────────────────────── */
    .ptah-base-crud.ptah-dark .p-4 label,
    .ptah-base-crud.ptah-dark .space-y-4 label:not(.flex) {
        color: #94a3b8; /* slate-400 */
    }
    .ptah-base-crud.ptah-dark .p-4 input:not([type="checkbox"]),
    .ptah-base-crud.ptah-dark .p-4 select {
        border-color: #475569;
        background-color: #1e293b;
        color: #e2e8f0;
    }
    .ptah-base-crud.ptah-dark .p-4 input:not([type="checkbox"]):focus,
    .ptah-base-crud.ptah-dark .p-4 select:focus {
        border-color: #818CF8;
        box-shadow: 0 0 0 3px rgba(129, 140, 248, .15);
    }

    /* Table header sticky shadow line */
    .ptah-cols-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
    }

    /* Row hover highlight */
    .ptah-base-crud tbody tr.ptah-tr {
        position: relative;
        transition: background-color .12s ease, box-shadow .12s ease;
    }
    .ptah-base-crud tbody tr.ptah-tr:hover {
        box-shadow: inset 3px 0 0 #5b21b6;
    }
    /* Em dark mode: accent mais suave */
    .ptah-base-crud.ptah-dark tbody tr.ptah-tr:hover {
        box-shadow: inset 3px 0 0 #7c3aed;
    }
    /* Botões de ação ficam opacos no hover da linha */
    .ptah-base-crud tbody tr.ptah-tr .ptah-row-btns {
        opacity: 0.35;
        transition: opacity .12s ease;
    }
    .ptah-base-crud tbody tr.ptah-tr:hover .ptah-row-btns {
        opacity: 1;
    }

    /* Row action buttons */
    .ptah-base-crud .ptah-row-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 6px;
        transition: background-color .12s, color .12s;
    }
    .ptah-base-crud .ptah-row-action:hover {
        background-color: #F1F5F9;
    }
</style>

<div id="ptah-resize-indicator"></div>

<script>
(function () {
    if (window.__ptahColDragInit) return;
    window.__ptahColDragInit = true;

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

})();
</script>

{{-- ═══════════════════════════════════════════════════════
     EXPORT LISTENERS (Excel/PDF Download)
    ══════════════════════════════════════════════════════════ --}}
<script>
document.addEventListener('livewire:init', () => {
    if (window.__ptahExportInit) return;
    window.__ptahExportInit = true;

    // Listener para exportação síncrona (Excel/PDF)
    Livewire.on('ptah:export-sync', (event) => {
        const data = Array.isArray(event) ? event[0] : event;
        const { model, format, filters, columns } = data;
        
        const params = new URLSearchParams({
            model: model,
            format: format || 'excel',
            filters: JSON.stringify(filters || {}),
            columns: JSON.stringify(columns || [])
        });
        
        const url = `/ptah/export?${params.toString()}`;
        window.open(url, '_blank');
    });

    // Listener para exportação em massa (itens selecionados)
    Livewire.on('ptah:bulk-export', (event) => {
        const data = Array.isArray(event) ? event[0] : event;
        const { model, ids, format, columns } = data;
        
        const params = new URLSearchParams({
            model: model,
            format: format || 'excel',
            ids: JSON.stringify(ids || []),
            columns: JSON.stringify(columns || [])
        });
        
        const url = `/ptah/export/bulk?${params.toString()}`;
        window.open(url, '_blank');
    });
});
</script>
@endonce
