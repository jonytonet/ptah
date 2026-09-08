<?php

declare(strict_types=1);

namespace Ptah\Support\AI;

use Illuminate\Support\Str;
use Throwable;

/**
 * Renders an assistant answer as HTML.
 *
 * The bubble used to be `nl2br(e($content))`, so a model that answered with a
 * table, a numbered list or a code block put `**negrito**`, `| a | b |` and
 * fenced backticks on screen as literal characters. Models answer in Markdown
 * whether or not the client renders it — that is the house style of every
 * instruction-tuned model — and an ERP assistant that lists records is
 * answering in tables most of the time.
 *
 * No new dependency: `laravel/framework` REQUIRES `league/commonmark` (not
 * suggests), so `Str::markdown()` is present in every host by construction.
 *
 * ── Why this is not just a call to Str::markdown ──────────────────────────
 *
 * The input is text a remote model produced, influenced by whatever the user
 * typed and by whatever a tool returned. It is untrusted. Three things follow:
 *
 * 1. `html_input => 'escape'`. A model that emits `<script>` — because a user
 *    asked it to, or because a tool result contained it — must not have that
 *    reach the DOM. Escaped, not stripped: stripping silently changes the
 *    answer, escaping shows what the model actually said.
 *
 * 2. `allow_unsafe_links => false`. CommonMark then refuses `javascript:`,
 *    `data:` and `vbscript:` hrefs. This is the same class of hole the row
 *    actions had, and HTML escaping does NOT close it — the scheme lives
 *    inside an attribute value that escaping leaves intact.
 *
 * 3. A failure renders as text. A malformed document, a pathological nesting
 *    depth, an extension throwing — none of that may take down a chat answer
 *    that the model already produced and that the user is waiting to read.
 */
final class ChatMarkdown
{
    /**
     * Rendering is also called on every streamed delta, where the same prefix
     * is converted again and again. Keyed by the text itself.
     *
     * @var array<string, string>
     */
    private static array $cache = [];

    private const CACHE_LIMIT = 64;

    public static function render(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        if (isset(self::$cache[$text])) {
            return self::$cache[$text];
        }

        try {
            $html = Str::markdown($text, [
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
                // A model asked to "draw a deep list" should not be able to
                // spend the request's memory on nesting.
                'max_nesting_level' => 20,
                // Uma quebra de linha simples vira <br>. Sem isto o GFM do
                // CommonMark junta as duas linhas num paragrafo so e o
                // navegador colapsa a quebra num espaco — e modelo responde em
                // linhas curtas separadas por uma quebra o tempo todo. Era o
                // comportamento do nl2br que este renderizador substitui, e
                // perde-lo seria regressao. Um teste proprio pegou isso.
                'renderer' => ['soft_break' => '<br />'],
            ]);
        } catch (Throwable) {
            // Plain text, escaped, with line breaks kept — exactly the old
            // behaviour, which is the right thing to degrade to.
            $html = nl2br(e($text), false);
        }

        if (count(self::$cache) >= self::CACHE_LIMIT) {
            self::$cache = [];
        }

        return self::$cache[$text] = $html;
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
