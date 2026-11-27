{{-- The header of a dragging dialog (see ptah::partials.drag-panel). --}}
@pointerdown="_dragStart($event)" @pointermove="_dragMove($event)" @pointerup="_dragEnd()" @pointercancel="_dragEnd()" style="touch-action: none"
