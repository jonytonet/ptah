/**
 * "Copy" and "Filter by this value" over a cell of the listing — the two
 * small buttons of the Livewire table (_scripts.blade.php), without a
 * framework so React and Vue only mark the cells.
 *
 * One group of buttons for the whole screen, placed over the cell the mouse
 * stopped on (~300 ms): it is not part of the table's layout, changes no
 * column width and does not flicker while the mouse crosses the rows. It
 * lives outside the `<tr>`, so clicking it does not open the record.
 *
 * A cell takes part when it carries `data-ptah-cell` (the column) and
 * `data-ptah-row` (the record); `data-ptah-filter` marks a filterable column.
 */
import type { CrudController } from '../controller';
import { filterByCell } from './filters';

export interface CellActionWords {
    copy: string;
    filter: string;
    copied: string;
    copyFailed: string;
}

const WORDS: Record<'en' | 'pt', CellActionWords> = {
    en: { copy: 'Copy', filter: 'Filter by this value', copied: 'Copied', copyFailed: 'Could not copy' },
    pt: { copy: 'Copiar', filter: 'Filtrar por este valor', copied: 'Copiado', copyFailed: 'Não foi possível copiar' },
};

/** The words of ptah's lang files (`crud_copy_cell`, `crud_filter_by_cell`, `crud_copied`, `crud_copy_failed`). */
export function cellActionWords(locale?: string | null): CellActionWords {
    return (locale ?? '').toLowerCase().startsWith('pt') ? WORDS.pt : WORDS.en;
}

export const CELL_ACTION_DELAY = 300;

const ICON_COPY =
    '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>';
const ICON_FILTER =
    '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z"/></svg>';

/** The text a person sees in the cell (the formatted value). */
export function cellText(td: HTMLElement): string {
    return (td.innerText ?? td.textContent ?? '').trim();
}

/**
 * Copies with the Clipboard API, or the old way where it is missing (http
 * outside localhost, an old browser) or refused. Answers whether it copied:
 * staying quiet on a failure would look like it worked.
 */
export async function copyToClipboard(text: string): Promise<boolean> {
    if (typeof navigator !== 'undefined' && navigator.clipboard && (typeof window === 'undefined' || window.isSecureContext !== false)) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            // Refused: try the old way below.
        }
    }

    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    let ok = false;
    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }
    area.remove();

    return ok;
}

export interface CellActionsOptions {
    words: CellActionWords;
    /** Copy is on for this screen (`uiPreferences.copyCell`); off, nothing is offered. */
    enabled: () => boolean;
    /** ms the mouse must rest on a cell (tests pass 0). */
    delay?: number;
}

/**
 * Turns the buttons on for the cells of this screen — those under the element
 * marked `data-ptah-crud="<screen key>"`, so a detail grid nested in a row
 * answers for its own cells and the parent for its own. Returns the function
 * that turns them off (call it when the screen is unmounted).
 */
export function installCellActions(crud: CrudController, options: CellActionsOptions): () => void {
    const delay = options.delay ?? CELL_ACTION_DELAY;
    let group: HTMLDivElement | null = null;
    let filterButton: HTMLButtonElement | null = null;
    let current: HTMLElement | null = null;
    let showTimer: ReturnType<typeof setTimeout> | null = null;
    let hideTimer: ReturnType<typeof setTimeout> | null = null;

    const clear = (timer: ReturnType<typeof setTimeout> | null) => {
        if (timer !== null) {
            clearTimeout(timer);
        }
    };

    const hide = (after: number) => {
        clear(showTimer);
        clear(hideTimer);
        hideTimer = setTimeout(() => {
            group?.classList.remove('is-visible');
            current = null;
        }, after);
    };

    const button = (label: string, icon: string, run: (td: HTMLElement) => void): HTMLButtonElement => {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = 'pc-cell-action';
        element.setAttribute('aria-label', label);
        element.title = label;
        element.innerHTML = icon;
        element.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (current) {
                run(current);
            }
            hide(0);
        });

        return element;
    };

    const build = (): HTMLDivElement => {
        if (group) {
            return group;
        }
        group = document.createElement('div');
        group.className = 'pc-cell-actions';
        const copy = button(options.words.copy, ICON_COPY, (td) => {
            void copyToClipboard(cellText(td)).then((ok) => crud.toast(ok ? options.words.copied : options.words.copyFailed, ok ? 'success' : 'danger'));
        });
        // The server reads the value of the RECORD (not the formatted text)
        // and applies that column's filter.
        filterButton = button(options.words.filter, ICON_FILTER, (td) => {
            void filterByCell(crud, td.dataset.ptahRow ?? '', td.dataset.ptahCell ?? '');
        });
        group.append(copy, filterButton);
        group.addEventListener('mouseenter', () => clear(hideTimer));
        group.addEventListener('mouseleave', () => hide(150));
        document.body.appendChild(group);

        return group;
    };

    const eligible = (td: HTMLElement | null): td is HTMLElement =>
        !!td && (td.closest('[data-ptah-crud]') as HTMLElement | null)?.dataset.ptahCrud === crud.screen && options.enabled() && td.dataset.ptahCell !== undefined && !td.querySelector('input, select, textarea, button') && cellText(td) !== '';

    const show = (td: HTMLElement) => {
        const element = build();
        const canFilter = td.dataset.ptahFilter !== undefined && (td.dataset.ptahRow ?? '') !== '';
        if (filterButton) {
            filterButton.style.display = canFilter ? '' : 'none';
        }
        const rect = td.getBoundingClientRect();
        current = td;
        element.style.top = `${rect.top + 4}px`;
        element.style.left = `${rect.right - (canFilter ? 52 : 26)}px`;
        element.classList.add('is-visible');
    };

    const onOver = (event: Event) => {
        const target = event.target as HTMLElement | null;
        if (group && target && group.contains(target)) {
            return;
        }
        const td = (target?.closest ? target.closest('td') : null) as HTMLElement | null;
        if (td && td === current) {
            clear(hideTimer);

            return;
        }
        clear(showTimer);
        if (!eligible(td)) {
            if (current) {
                hide(150);
            }

            return;
        }
        if (current) {
            hide(0);
        }
        showTimer = setTimeout(() => show(td), delay);
    };
    // Fixed on the screen: after a scroll the position would no longer match the cell.
    const onScroll = () => hide(0);

    document.addEventListener('mouseover', onOver);
    window.addEventListener('scroll', onScroll, true);

    return () => {
        document.removeEventListener('mouseover', onOver);
        window.removeEventListener('scroll', onScroll, true);
        clear(showTimer);
        clear(hideTimer);
        group?.remove();
        group = null;
        current = null;
    };
}
