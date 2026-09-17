<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Facades\Log;

/**
 * Which models own rows in `user_preferences`, and a warning when there is more
 * than one.
 *
 * The table cannot tell two identities apart: its unique key is
 * `['user_id', 'key']`, so user 1 of one identity and user 1 of another compete
 * for the same row — last write wins, with no error — and the cleanup on delete
 * matches `user_id` alone, so removing one identity's user takes the other's
 * preferences with it.
 *
 * The way a host arrives there is ordinary: it switches identity and leaves
 * `HasUserPreferences` on the `App\Models\User` that came with the scaffold,
 * beside the model it actually uses. Nothing collides while `users` is empty,
 * and one row in it is enough to start.
 *
 * ── Why this is a class and not a static in the trait ────────────────────
 *
 * A static property declared in a TRAIT is per using-class: every model that
 * uses the trait gets its own copy, so a counter kept there would always read
 * 1 and the warning could never fire. The first version of this did exactly
 * that. The registry has to live somewhere single.
 */
final class PreferenceOwners
{
    /**
     * @var array<class-string, true>
     */
    private static array $owners = [];

    private static bool $warned = false;

    /**
     * @param  class-string  $model
     */
    public static function register(string $model): void
    {
        self::$owners[$model] = true;

        if (count(self::$owners) < 2 || self::$warned || self::declaredMultiIdentity()) {
            return;
        }

        self::$warned = true;

        Log::warning(
            '[Ptah] HasUserPreferences esta em mais de um model: '.implode(', ', self::all()).'. '.
            'A tabela user_preferences nao distingue donos — o unique e (user_id, key), entao ids '.
            'iguais em identidades diferentes disputam a MESMA linha, e a limpeza no delete apaga '.
            'as duas. Aplique o trait a uma identidade so, ou declare PTAH_PREFERENCES_FK=false se '.
            'os espacos de id forem disjuntos.'
        );
    }

    /**
     * @return list<class-string>
     */
    public static function all(): array
    {
        return array_keys(self::$owners);
    }

    /**
     * Forget everything — for tests, which boot models across cases.
     */
    public static function flush(): void
    {
        self::$owners = [];
        self::$warned = false;
    }

    /**
     * A host that set `foreign_key = false` has SAID it runs more than one
     * identity, and is taken to know its id spaces are disjoint. Warning there
     * would be noise aimed at the one person who already read the note.
     */
    private static function declaredMultiIdentity(): bool
    {
        $declared = config('ptah.preferences.foreign_key', 'auto');

        return $declared === false || $declared === 'false' || $declared === 0 || $declared === '0';
    }
}
