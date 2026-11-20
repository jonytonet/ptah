import { afterEach, describe, expect, it } from 'vitest';
import { AI_STORAGE, chatToEnd, noteChatScroll, placeChatScroll } from '../../core/admin/ai';

/** A list with the geometry of a real one (jsdom has no layout). */
function list(scrollHeight: number, clientHeight: number, scrollTop = 0): HTMLElement {
    const el = document.createElement('div');
    document.body.appendChild(el);
    Object.defineProperty(el, 'scrollHeight', { configurable: true, get: () => scrollHeight });
    Object.defineProperty(el, 'clientHeight', { configurable: true, get: () => clientHeight });
    el.scrollTop = scrollTop;

    return el;
}

const kept = () => window.sessionStorage.getItem(AI_STORAGE.scroll);

afterEach(() => {
    window.sessionStorage.clear();
    document.body.innerHTML = '';
});

describe('where the reader is in the chat (1.59.2)', () => {
    it('reading an older message is a position; at the end is none', () => {
        const el = list(1000, 200, 300);
        noteChatScroll(el);
        expect(kept()).toBe('300');

        el.scrollTop = 790; // 10 px from the end
        noteChatScroll(el);
        expect(kept()).toBeNull();
    });

    it('a closed chat (no height) or a list out of the document is not a position', () => {
        const closed = list(1000, 0, 0);
        window.sessionStorage.setItem(AI_STORAGE.scroll, '300');
        noteChatScroll(closed);
        expect(kept()).toBe('300');

        const detached = list(1000, 200, 0);
        detached.remove();
        noteChatScroll(detached);
        expect(kept()).toBe('300');
    });

    it('reopening or reloading puts the list where the reader was, else at the end', () => {
        window.sessionStorage.setItem(AI_STORAGE.scroll, '300');
        const el = list(1000, 200, 0);
        placeChatScroll(el);
        expect(el.scrollTop).toBe(300);

        window.sessionStorage.removeItem(AI_STORAGE.scroll);
        const other = list(1000, 200, 0);
        placeChatScroll(other);
        expect(other.scrollTop).toBe(1000);
    });

    it('sending goes to the end; a broken value is the end', () => {
        window.sessionStorage.setItem(AI_STORAGE.scroll, '300');
        chatToEnd();
        expect(kept()).toBeNull();

        window.sessionStorage.setItem(AI_STORAGE.scroll, 'abc');
        const el = list(1000, 200, 0);
        placeChatScroll(el);
        expect(el.scrollTop).toBe(1000);
    });
});
