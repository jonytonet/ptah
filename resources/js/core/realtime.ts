/**
 * Updates over a websocket — what `broadcast` in a screen's config and
 * `ptah.notifications.broadcast` do on the Livewire screens, where Livewire
 * registers an Echo listener and re-renders.
 *
 * ptah does not ship a websocket client: as on Livewire, the app sets up
 * Laravel Echo (Reverb, Pusher, Ably…) and leaves it on `window.Echo`. With no
 * Echo on the page nothing is listened to and the screen works as before.
 */

/** A listener as the server describes it (Ptah\Support\BroadcastListener). */
export interface BroadcastSpec {
    /** `public` (anyone with the key may subscribe), `private` or `presence` (authorised by the app). */
    type: 'public' | 'private' | 'presence';
    channel: string;
    /** The event name; a leading dot means "as it is, without the app's namespace". */
    event: string;
}

interface EchoChannel {
    listen(event: string, callback: (payload: unknown) => void): unknown;
    stopListening?(event: string, callback?: (payload: unknown) => void): unknown;
}

/** The part of Laravel Echo this needs. */
export interface EchoLike {
    channel(name: string): EchoChannel;
    private(name: string): EchoChannel;
    join(name: string): EchoChannel;
    leave?(name: string): void;
}

/** How many listeners each channel has, so the last one to go leaves it. */
const subscribers = new WeakMap<object, Map<string, number>>();

function echoOf(echo?: EchoLike | null): EchoLike | null {
    if (echo) {
        return echo;
    }
    const found = typeof window !== 'undefined' ? (window as unknown as { Echo?: EchoLike }).Echo : undefined;

    return found && typeof found.channel === 'function' ? found : null;
}

/**
 * Calls `onEvent` each time the event arrives on the channel. Returns the
 * function that stops listening. Does nothing (and returns a no-op) when
 * there is no spec or no Echo on the page.
 */
export function listenBroadcast(spec: BroadcastSpec | null | undefined, onEvent: (payload: unknown) => void, echo?: EchoLike | null): () => void {
    const client = echoOf(echo);
    if (!spec || !client || !spec.channel || !spec.event) {
        return () => undefined;
    }

    let channel: EchoChannel;
    try {
        channel = spec.type === 'private' ? client.private(spec.channel) : spec.type === 'presence' ? client.join(spec.channel) : client.channel(spec.channel);
        channel.listen(spec.event, onEvent);
    } catch {
        // A misconfigured Echo must not take the screen down.
        return () => undefined;
    }

    const key = `${spec.type}:${spec.channel}`;
    const counts = subscribers.get(client) ?? new Map<string, number>();
    subscribers.set(client, counts);
    counts.set(key, (counts.get(key) ?? 0) + 1);

    let stopped = false;

    return () => {
        if (stopped) {
            return;
        }
        stopped = true;
        try {
            channel.stopListening?.(spec.event, onEvent);
            const left = (counts.get(key) ?? 1) - 1;
            if (left <= 0) {
                counts.delete(key);
                client.leave?.(spec.channel);
            } else {
                counts.set(key, left);
            }
        } catch {
            // Already gone.
        }
    };
}
