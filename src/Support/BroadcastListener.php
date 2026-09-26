<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Str;

/**
 * The Echo listener a BaseCrud screen registers for `broadcast` in its config.
 *
 *   "broadcast": { "enabled": true, "type": "private", "perCompany": true,
 *                  "channel": null, "event": null }
 *
 * Until 1.39.0 only `echo:` existed — a PUBLIC channel, which anyone holding
 * the Reverb/Pusher key (it ships in the page's JavaScript) can subscribe to.
 * `private` and `presence` make Echo authorise the subscription through the
 * host's `Broadcast::channel()` callback. `perCompany` appends the active
 * company to the channel, so one branch's change does not refresh another
 * branch's screens — and, with `private`, the callback can refuse the others.
 *
 * One function, used by the component and by the editor's preview, so the
 * preview cannot describe a listener the screen does not register.
 */
final class BroadcastListener
{
    public const TYPES = ['public' => 'echo', 'private' => 'echo-private', 'presence' => 'echo-presence'];

    /**
     * `echo[-private|-presence]:{channel},{event}`, or null when broadcast is off.
     *
     * @param  array<string, mixed>  $broadcast
     */
    public static function key(array $broadcast, string $model, int $companyId = 0): ?string
    {
        if (empty($broadcast['enabled'])) {
            return null;
        }

        return self::prefix($broadcast).':'.self::channel($broadcast, $model, $companyId).','.self::event($broadcast, $model);
    }

    /**
     * @param  array<string, mixed>  $broadcast
     */
    public static function type(array $broadcast): string
    {
        $type = (string) ($broadcast['type'] ?? (! empty($broadcast['private']) ? 'private' : 'public'));

        return array_key_exists($type, self::TYPES) ? $type : 'public';
    }

    /**
     * @param  array<string, mixed>  $broadcast
     */
    public static function channel(array $broadcast, string $model, int $companyId = 0): string
    {
        $channel = ! empty($broadcast['channel'])
            ? (string) $broadcast['channel']
            : 'page-'.Str::kebab(class_basename(str_replace('/', '\\', $model))).'-observer';

        return ! empty($broadcast['perCompany']) && $companyId > 0 ? $channel.'.'.$companyId : $channel;
    }

    /**
     * @param  array<string, mixed>  $broadcast
     */
    public static function event(array $broadcast, string $model): string
    {
        return ! empty($broadcast['event'])
            ? (string) $broadcast['event']
            : '.page'.class_basename(str_replace('/', '\\', $model)).'Observer';
    }

    /**
     * @param  array<string, mixed>  $broadcast
     */
    private static function prefix(array $broadcast): string
    {
        return self::TYPES[self::type($broadcast)];
    }
}
