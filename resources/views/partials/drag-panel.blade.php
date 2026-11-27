{{--
    A dialog panel that drags by its header (forge-modal draggable, 1.60.3;
    shared by every Livewire dialog since 1.63.2). Goes on the PANEL element:
        @include('ptah::partials.drag-panel', ['open' => 'open'])
    and the header takes @include('ptah::partials.drag-handle').
    `open`: the Alpine expression of the dialog being open — closing puts it
    back in the centre. `translate`, not `transform`: the opening transition
    scales with transform, and the two compose. The pointer is held to the
    window, so the header never leaves it. :style as an object: a string
    would replace the panel's own inline style (the filter dialog's size).
--}}
        x-data="{
            _dx: 0, _dy: 0, _from: null,
            _dragStart(e) {
                if (e.button !== 0 || e.target.closest('button, a, input, select, textarea')) return;
                this._from = { x: e.clientX - this._dx, y: e.clientY - this._dy };
                e.currentTarget.setPointerCapture(e.pointerId);
            },
            _dragMove(e) {
                if (! this._from) return;
                const x = Math.min(Math.max(e.clientX, 0), window.innerWidth);
                const y = Math.min(Math.max(e.clientY, 0), window.innerHeight - 24);
                this._dx = x - this._from.x;
                this._dy = y - this._from.y;
            },
            _dragEnd() { this._from = null; },
        }"
        x-effect="if (! ({{ $open ?? 'open' }})) { _dx = 0; _dy = 0; }"
        :style="{ translate: _dx + 'px ' + _dy + 'px' }"
        data-ptah-draggable
