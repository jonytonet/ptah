import { afterEach, describe, expect, it, vi } from 'vitest';
import { CrudController } from '../../core/controller';
import { listenBroadcast } from '../../core/realtime';
import type { EchoLike } from '../../core/realtime';
import { fakeApi, schema } from '../fake-api';

/** An Echo that records what was asked of it and lets a test fire an event. */
function fakeEcho() {
    const listeners = new Map<string, Set<(payload: unknown) => void>>();
    const log: string[] = [];
    const channel = (kind: string, name: string) => ({
        listen(event: string, callback: (payload: unknown) => void) {
            log.push(`${kind}:${name}:listen:${event}`);
            const key = `${name},${event}`;
            listeners.set(key, (listeners.get(key) ?? new Set()).add(callback));
        },
        stopListening(event: string, callback?: (payload: unknown) => void) {
            log.push(`${kind}:${name}:stop:${event}`);
            if (callback) {
                listeners.get(`${name},${event}`)?.delete(callback);
            }
        },
    });
    const echo: EchoLike = {
        channel: (name) => channel('public', name),
        private: (name) => channel('private', name),
        join: (name) => channel('presence', name),
        leave: (name) => void log.push(`leave:${name}`),
    };
    const fire = (name: string, event: string, payload: unknown = {}) => listeners.get(`${name},${event}`)?.forEach((callback) => callback(payload));

    return { echo, log, fire };
}

afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    delete (schema as { broadcast?: unknown }).broadcast;
});

describe('listenBroadcast', () => {
    it('subscribes by the kind of channel and hears the event', () => {
        const { echo, log, fire } = fakeEcho();
        const heard = vi.fn();

        listenBroadcast({ type: 'private', channel: 'orders.3', event: '.OrderChanged' }, heard, echo);
        listenBroadcast({ type: 'presence', channel: 'room', event: '.Typing' }, heard, echo);
        listenBroadcast({ type: 'public', channel: 'news', event: '.Posted' }, heard, echo);

        expect(log).toEqual(['private:orders.3:listen:.OrderChanged', 'presence:room:listen:.Typing', 'public:news:listen:.Posted']);
        fire('orders.3', '.OrderChanged', { id: 9 });
        expect(heard).toHaveBeenCalledWith({ id: 9 });
    });

    it('the last listener of a channel leaves it; an earlier one only stops listening', () => {
        const { echo, log } = fakeEcho();
        const spec = { type: 'public' as const, channel: 'items', event: '.Changed' };

        const first = listenBroadcast(spec, () => undefined, echo);
        const second = listenBroadcast(spec, () => undefined, echo);
        first();
        first();
        expect(log.filter((line) => line.startsWith('leave'))).toEqual([]);

        second();
        expect(log.filter((line) => line.startsWith('leave'))).toEqual(['leave:items']);
    });

    it('does nothing without a spec or without Echo on the page', () => {
        const { echo, log } = fakeEcho();

        expect(() => listenBroadcast(null, () => undefined, echo)()).not.toThrow();
        expect(() => listenBroadcast({ type: 'public', channel: 'items', event: '.Changed' }, () => undefined)()).not.toThrow();
        expect(log).toEqual([]);
    });

    it('a broken Echo does not take the screen down', () => {
        const broken = { channel: () => { throw new Error('no socket'); }, private: () => { throw new Error('x'); }, join: () => { throw new Error('x'); } } as unknown as EchoLike;

        expect(() => listenBroadcast({ type: 'public', channel: 'items', event: '.Changed' }, () => undefined, broken)()).not.toThrow();
    });
});

describe('a screen with `broadcast` on', () => {
    it('reloads the listing, once for a burst of events, and stops when the screen goes away', async () => {
        const { echo, log, fire } = fakeEcho();
        vi.stubGlobal('Echo', echo);
        (schema as { broadcast?: unknown }).broadcast = { type: 'private', channel: 'items.1', event: '.ItemChanged' };
        const fake = fakeApi();
        const crud = new CrudController('items', { api: fake.api });
        await crud.init();
        expect(log).toEqual(['private:items.1:listen:.ItemChanged']);

        const listings = () => fake.calls.filter((call) => call.method === 'GET' && /\/crud\/items(\?|$)/.test(call.url)).length;
        const before = listings();
        vi.useFakeTimers();

        fire('items.1', '.ItemChanged');
        fire('items.1', '.ItemChanged');
        fire('items.1', '.ItemChanged');
        // Quiet: the screen is not dimmed for a refresh nobody asked for.
        expect(crud.getState().busy).toBe(false);
        await vi.advanceTimersByTimeAsync(500);
        vi.useRealTimers();
        await vi.waitFor(() => expect(listings()).toBe(before + 1));

        crud.destroy();
        expect(log).toContain('leave:items.1');
        vi.useFakeTimers();
        fire('items.1', '.ItemChanged');
        await vi.advanceTimersByTimeAsync(500);
        vi.useRealTimers();
        expect(listings()).toBe(before + 1);
    });

    it('a screen without `broadcast` subscribes to nothing', async () => {
        const { echo, log } = fakeEcho();
        vi.stubGlobal('Echo', echo);
        const crud = new CrudController('items', { api: fakeApi().api });
        await crud.init();

        expect(log).toEqual([]);
        crud.destroy();
    });
});
