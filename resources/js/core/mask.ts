/**
 * The ptah input masks, while typing. Tokens: `0` a digit, `A` a letter, `*` a
 * letter or digit; anything else is a literal the mask inserts.
 *
 * A mask may have several shapes — `(00) 0000-0000` and `(00) 00000-0000` — and
 * the smallest one that still holds what was typed wins. What reaches the
 * database is decided by the server on save; this only formats the field.
 */

const TOKENS: Record<string, RegExp> = {
    '0': /\d/,
    A: /[A-Za-zÀ-ÿ]/,
    '*': /[A-Za-zÀ-ÿ0-9]/,
};

function slots(pattern: string): number {
    return [...pattern].filter((ch) => ch in TOKENS).length;
}

/** The characters of the input that can fill a slot of any of the patterns. */
function usable(raw: string): string[] {
    return [...raw].filter((ch) => TOKENS['*'].test(ch));
}

export function pickPattern(patterns: string[], raw: string): string {
    const count = usable(raw).length;
    const sorted = [...patterns].sort((a, b) => slots(a) - slots(b));

    return sorted.find((pattern) => slots(pattern) >= count) ?? sorted[sorted.length - 1] ?? '';
}

export function applyMask(patterns: string[] | null | undefined, raw: string): string {
    if (!patterns || patterns.length === 0 || raw === '') {
        return raw;
    }

    const pattern = pickPattern(patterns, raw);
    const chars = usable(raw);
    let out = '';
    let index = 0;

    for (const ch of pattern) {
        if (index >= chars.length) {
            break;
        }

        if (ch in TOKENS) {
            // Skip input that does not fit this slot (a letter where a digit goes).
            while (index < chars.length && !TOKENS[ch].test(chars[index])) {
                index++;
            }
            if (index >= chars.length) {
                break;
            }
            out += chars[index++];
        } else {
            out += ch;
        }
    }

    return out;
}
