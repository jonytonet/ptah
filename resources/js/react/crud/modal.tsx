/**
 * The dialog shell of the BaseCrud modals (forge-modal) and the yes/no
 * confirmation (_modal-delete), for React. The form modal, history,
 * attachments and import wizard draw inside it. Mirrors vue/crud/modal.ts.
 */
import { useEffect, useId, useRef } from 'react';
import type { ReactNode } from 'react';
import { modalButton } from '../../core/crud/form';
import { trapFocus } from '../../core/focus';
import { Icon } from '../icons';

export interface PtahCrudModalProps {
    title: ReactNode;
    subtitle?: ReactNode;
    /** forge-modal's size: sm 24rem, md 28rem, lg 32rem, xl 36rem, 2xl 42rem. */
    size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    /** The ✕, Escape and a click on the backdrop. */
    onClose: () => void;
    /** Name of the ✕ (`modal_close`). */
    closeLabel: string;
    footer?: ReactNode;
    children?: ReactNode;
    /** `alertdialog` for a confirmation. */
    role?: 'dialog' | 'alertdialog';
    /** Escape closes it (default). */
    escape?: boolean;
}

/**
 * The dialog of the Livewire screens (forge-modal): a blurred backdrop, a
 * rounded panel with a header (title, subtitle, ✕), a scrolling body and a
 * footer of buttons aligned right.
 */
export function PtahCrudModal({ title, subtitle, size = 'md', onClose, closeLabel, footer, children, role = 'dialog', escape = true }: PtahCrudModalProps) {
    const titleId = useId();
    const panel = useRef<HTMLDivElement>(null);

    // Tab stays in the dialog; closing gives the focus back (1.61.0).
    useEffect(() => (panel.current ? trapFocus(panel.current) : undefined), []);

    useEffect(() => {
        if (!escape) {
            return;
        }
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [escape, onClose]);

    return (
        <div className="pc-root pc-fm-back" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
            <div ref={panel} className="pc-fm-panel" data-size={size} role={role} aria-modal="true" aria-labelledby={titleId}>
                <div className="pc-fm-head">
                    <div>
                        <h3 className="pc-fm-title" id={titleId}>
                            {title}
                        </h3>
                        {subtitle && <p className="pc-fm-sub">{subtitle}</p>}
                    </div>
                    <button type="button" className="pc-fm-close" aria-label={closeLabel} title={closeLabel} onClick={onClose}>
                        <Icon name="x" size={20} />
                    </button>
                </div>
                {children !== undefined && children !== null && children !== false && <div className="pc-fm-body">{children}</div>}
                {footer && <div className="pc-fm-foot">{footer}</div>}
            </div>
        </div>
    );
}

export interface PtahConfirmDialogProps {
    /** The question — or, with a `title`, the message under it. */
    text: string;
    confirm: string;
    cancel: string;
    /** A destructive confirmation: the danger button and the warning icon. */
    danger?: boolean;
    /** With a title it is the forge-modal of `_modal-delete` (title, ✕, icon + message). */
    title?: string;
    /** Name of the ✕ (default: the cancel word). */
    closeLabel?: string;
    onConfirm: () => void;
    onCancel: () => void;
}

/** A yes/no question: the delete confirmation of the Livewire screen, or a plain one. Mirrors vue/PtahConfirm.vue. */
export function PtahConfirmDialog({ text, confirm, cancel, danger, title, closeLabel, onConfirm, onCancel }: PtahConfirmDialogProps) {
    const footer = (
        <>
            <button type="button" className={modalButton('flat')} onClick={onCancel}>
                {cancel}
            </button>
            <button type="button" className={modalButton(danger ? 'danger' : 'primary')} autoFocus onClick={onConfirm}>
                {confirm}
            </button>
        </>
    );

    return (
        <PtahCrudModal title={title ?? text} size="sm" role="alertdialog" closeLabel={closeLabel ?? cancel} onClose={onCancel} footer={footer}>
            {title && (
                <div className="pc-fm-warn">
                    {danger && (
                        <span className="pc-fm-warn-icon">
                            <Icon name="warning" size={20} />
                        </span>
                    )}
                    <p>{text}</p>
                </div>
            )}
        </PtahCrudModal>
    );
}

/** forge-empty, inside a modal. */
export function PtahModalEmpty({ title }: { title: string }) {
    return (
        <div className="pc-fm-empty">
            <span>
                <Icon name="inbox" size={32} strokeWidth={1.5} />
            </span>
            <p>{title}</p>
        </div>
    );
}

/** forge-alert, inside a modal. */
export function PtahModalAlert({ color = 'danger', children }: { color?: 'danger' | 'success' | 'warn'; children: ReactNode }) {
    return (
        <div className="pc-fm-alert" data-color={color} role={color === 'danger' ? 'alert' : 'status'}>
            {children}
        </div>
    );
}
