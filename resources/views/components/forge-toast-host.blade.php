{{--
    forge-toast-host — Ptah Forge

    Pilha global de toasts. Escuta o evento `ptah-toast` no window, entao QUALQUER
    componente Livewire ou codigo Alpine pode disparar um toast sem saber que este
    host existe:

        $this->dispatch('ptah-toast', title: 'Salvo', color: 'success');

    Antes isto vivia dentro de `base-crud.blade.php`, o que significava que toast era
    um privilegio de tela de CRUD — o /profile, por exemplo, so tinha alerta inline.
    Subir para o layout resolve para todas as telas de uma vez.

    O botao Desfazer nao chama mais `$wire.restoreRecord` direto (aqui nao existe
    `$wire`): ele re-emite `ptah-toast-undo` no window com o id, e quem sabe restaurar
    escuta. E o que desacopla a pilha do BaseCrud.

    Auto-dismiss pausa no hover/foco (WCAG 2.2.1 — Timing Adjustable): cada toast
    guarda a duracao total e quando o timer atual comecou, entao _pause() calcula
    quanto falta e cancela o setTimeout, e _resume() reagenda so o restante. Sem
    isso, um toast com Desfazer podia desaparecer com o mouse ainda em cima dele.

    ATENCAO: nenhuma aspa dupla dentro do x-data — ver LayoutXDataQuotingTest.

    Cores: os fundos usam os mesmos valores auditados do BaseCrud (#047857 para
    success e bg-danger-dark para danger, ambos com branco acima de 4.5:1 — ver
    ContrastGuardTest casos 13). `bg-warn` fica com texto escuro pelo mesmo motivo.
--}}
<div x-data="{
        _toasts: [],
        _seq: 0,
        _show(title, color, undoId = null) {
            if (!title) return;
            const id = ++this._seq;
            const duration = undoId ? 6000 : 3500;
            const toast = { id, title, color: color || 'success', undoId, duration, startedAt: Date.now(), timer: null };
            toast.timer = setTimeout(() => this._dismiss(id), duration);
            this._toasts.push(toast);
        },
        _dismiss(id) {
            this._toasts = this._toasts.filter(t => t.id !== id);
        },
        _pause(t) {
            clearTimeout(t.timer);
            t.duration = Math.max(0, t.duration - (Date.now() - t.startedAt));
        },
        _resume(t) {
            t.startedAt = Date.now();
            t.timer = setTimeout(() => this._dismiss(t.id), t.duration);
        },
        _undo(t) {
            window.dispatchEvent(new CustomEvent('ptah-toast-undo', { detail: { id: t.undoId } }));
            this._dismiss(t.id);
        }
     }"
     @ptah-toast.window="_show($event.detail.title, $event.detail.color, $event.detail.undoId ?? null)"
     class="fixed bottom-4 left-4 z-[60] flex flex-col gap-2 ptah-no-print"
     aria-live="polite">
    <template x-for="t in _toasts" :key="t.id">
        <div x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-2"
             @mouseenter="_pause(t)"
             @mouseleave="_resume(t)"
             @focusin="_pause(t)"
             @focusout="_resume(t)"
             class="flex items-center gap-2.5 px-4 py-3 rounded-lg shadow-lg text-sm font-semibold"
             :class="{
                 'bg-[#047857] text-white': t.color === 'success',
                 'bg-warn text-dark':       t.color === 'warn',
                 'bg-danger-dark text-white': t.color === 'danger',
                 'bg-primary text-white':  t.color === 'primary'
             }">
            <svg x-show="t.color === 'success'" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <svg x-show="t.color === 'warn'"    class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <svg x-show="t.color === 'danger'"  class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span x-text="t.title"></span>
            <button x-show="t.undoId" @click="_undo(t)"
                    class="ml-1 px-2 py-0.5 rounded bg-white/20 hover:bg-white/30 text-xs font-bold uppercase tracking-wide">
                {{ __('ptah::ui.toast_undo') }}
            </button>
            <button @click="_dismiss(t.id)" class="ml-1 opacity-70 hover:opacity-100"
                    aria-label="{{ __('ptah::ui.modal_close') }}">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </template>
</div>
