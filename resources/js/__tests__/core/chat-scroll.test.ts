import { afterEach, describe, expect, it } from 'vitest';
import { AI_STORAGE, chatToEnd, keepChatConversation, keptChatConversation, noteChatScroll, placeChatScroll } from '../../core/admin/ai';

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
        noteChatScroll(el, 4);
        expect(kept()).toBe('4:300');

        el.scrollTop = 790; // 10 px from the end
        noteChatScroll(el, 4);
        expect(kept()).toBeNull();
    });

    it('a closed chat (no height) or a list out of the document is not a position', () => {
        const closed = list(1000, 0, 0);
        window.sessionStorage.setItem(AI_STORAGE.scroll, '4:300');
        noteChatScroll(closed, 4);
        expect(kept()).toBe('4:300');

        const detached = list(1000, 200, 0);
        detached.remove();
        noteChatScroll(detached, 4);
        expect(kept()).toBe('4:300');
    });

    it('reopening or reloading puts the list where the reader was, else at the end', () => {
        window.sessionStorage.setItem(AI_STORAGE.scroll, '4:300');
        const el = list(1000, 200, 0);
        placeChatScroll(el, 4);
        expect(el.scrollTop).toBe(300);

        window.sessionStorage.removeItem(AI_STORAGE.scroll);
        const other = list(1000, 200, 0);
        placeChatScroll(other, 4);
        expect(other.scrollTop).toBe(1000);
    });

    it('sending goes to the end; a broken value is the end', () => {
        window.sessionStorage.setItem(AI_STORAGE.scroll, '4:300');
        chatToEnd();
        expect(kept()).toBeNull();

        window.sessionStorage.setItem(AI_STORAGE.scroll, 'abc');
        const el = list(1000, 200, 0);
        placeChatScroll(el, 4);
        expect(el.scrollTop).toBe(1000);
    });

    it('a position taken in another conversation is not used: that one opens at the end', () => {
        window.sessionStorage.setItem(AI_STORAGE.scroll, '4:300');
        const el = list(1000, 200, 0);
        placeChatScroll(el, 9);
        expect(el.scrollTop).toBe(1000);
    });

    it('keeps the conversation on screen for a reload', () => {
        expect(keptChatConversation()).toBeNull();
        keepChatConversation(4);
        expect(keptChatConversation()).toBe(4);
        keepChatConversation(null);
        expect(keptChatConversation()).toBeNull();
        window.sessionStorage.setItem(AI_STORAGE.conversation, 'abc');
        expect(keptChatConversation()).toBeNull();
    });
});
