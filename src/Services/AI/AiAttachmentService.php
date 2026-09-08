<?php

declare(strict_types=1);

namespace Ptah\Services\AI;

use Illuminate\Support\Facades\Log;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Text;
use Throwable;

/**
 * What a user may attach, and how it reaches the model.
 *
 * ── The problem this exists to prevent ───────────────────────────────────
 *
 * Prism's providers do not all understand the same message parts, and the
 * mismatch is SILENT: a part a provider's MessageMap does not handle is simply
 * not serialised. The request succeeds, the model answers about a document it
 * never received, and nothing anywhere says so. A confidently wrong answer is
 * the worst outcome this feature could produce.
 *
 * Measured against the installed Prism rather than assumed (grep of every
 * `src/Providers/*\/Maps/`):
 *
 *   Image     every text provider maps it, xAI/Grok included.
 *   Document  anthropic, gemini, mistral, openai, openrouter, perplexity, z.
 *             NOT xai, groq, deepseek, ollama.
 *
 * ── The rule ─────────────────────────────────────────────────────────────
 *
 * Nothing is offered that does not work. The allowlist is narrowed by the
 * SELECTED provider, so on a document-blind provider the file picker does not
 * accept a PDF, a dropped PDF is refused, and the refusal says why. Offering an
 * attachment that gets dropped in transit would be worse than not offering it.
 *
 * One exception, and it is not a compromise: a `.txt`, `.md`, `.csv` or `.json`
 * IS text. Sending it as a Text part loses nothing — there is no layout, no page
 * image, no embedded object to lose — so those travel inline on every provider
 * rather than being refused on four of them. A PDF or a DOCX is not text; a
 * conversion would lose the thing that makes it a document, so those are native
 * or not at all.
 *
 * That is also why there is no PDF parser dependency here. Extracting text from
 * a PDF to fake support on a provider that cannot take one would be exactly the
 * silent degradation this class refuses.
 */
final class AiAttachmentService
{
    /**
     * Providers whose Prism MessageMap serialises a Document part.
     *
     * Keep in step with the vendored Prism when it is upgraded: a provider added
     * here that cannot really take documents reintroduces the silent-drop bug
     * this list exists to prevent. AiAttachmentCapabilityTest checks every entry
     * against Prism's own source, and checks the excluded ones too.
     *
     * @var list<string>
     */
    public const DOCUMENT_PROVIDERS = [
        'anthropic',
        'gemini',
        'mistral',
        'openai',
        'openrouter',
        'perplexity',
        'z',
    ];

    /** Extensions that are text, so inlining them is lossless. */
    public const TEXT_EXTENSIONS = ['txt', 'md', 'csv', 'tsv', 'json', 'log'];

    /** Extensions handled as images — native on every provider. */
    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];

    /**
     * @return array{images: bool, documents: bool}
     */
    public function capabilities(string $provider): array
    {
        return [
            // Universal in Prism today. Whether the chosen MODEL has vision is a
            // different question this package cannot answer — a text-only model
            // given an image errors from the provider, which ProviderFailure
            // already classifies and reports.
            'images' => true,
            'documents' => in_array(strtolower(trim($provider)), self::DOCUMENT_PROVIDERS, true),
        ];
    }

    /**
     * The configured allowlist, narrowed to what this provider can actually
     * receive. This is the single source for the file picker's `accept`, the
     * client-side filter and the server-side validation — three places that
     * must never disagree about what is allowed.
     *
     * @return list<string>
     */
    public function allowedExtensions(string $provider): array
    {
        $configured = array_values(array_unique(array_map(
            static fn ($e): string => strtolower(ltrim(trim((string) $e), '.')),
            (array) config('ptah.ai_agent.attachments.allowed_extensions', [])
        )));

        $documents = $this->capabilities($provider)['documents'];

        return array_values(array_filter($configured, function (string $ext) use ($documents): bool {
            if ($ext === '') {
                return false;
            }

            if (in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                return true;
            }

            if (in_array($ext, self::TEXT_EXTENSIONS, true)) {
                return true;
            }

            // Everything else is a document: only where the provider takes one.
            return $documents;
        }));
    }

    /**
     * Extensions the configuration allows but this provider cannot receive.
     * The widget shows these so the refusal is legible before it happens.
     *
     * @return list<string>
     */
    public function blockedExtensions(string $provider): array
    {
        $configured = array_map(
            static fn ($e): string => strtolower(ltrim(trim((string) $e), '.')),
            (array) config('ptah.ai_agent.attachments.allowed_extensions', [])
        );

        $allowed = $this->allowedExtensions($provider);

        return array_values(array_unique(array_filter(
            $configured,
            static fn (string $e): bool => $e !== '' && ! in_array($e, $allowed, true)
        )));
    }

    /**
     * Builds the Prism parts for one turn.
     *
     * @param  array<int, array{path: string, name: string, mime: string}>  $files
     * @return array{parts: array<int, Image|Document|Text>, notes: list<string>}
     */
    public function toPrismParts(array $files, string $provider): array
    {
        $caps = $this->capabilities($provider);
        $parts = [];
        $notes = [];

        foreach ($files as $file) {
            $path = (string) ($file['path'] ?? '');
            $name = (string) ($file['name'] ?? basename($path));
            $mime = strtolower((string) ($file['mime'] ?? ''));
            $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

            if ($path === '' || ! is_file($path)) {
                $notes[] = trans('ptah::ui.ai_attach_note_missing', ['name' => $name]);

                continue;
            }

            try {
                if (str_starts_with($mime, 'image/') || in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                    $parts[] = Image::fromLocalPath($path);

                    continue;
                }

                if (in_array($ext, self::TEXT_EXTENSIONS, true) || str_starts_with($mime, 'text/')) {
                    $text = $this->readText($path);

                    if ($text === null || trim($text) === '') {
                        $notes[] = trans('ptah::ui.ai_attach_note_empty', ['name' => $name]);

                        continue;
                    }

                    $parts[] = new Text(
                        trans('ptah::ui.ai_attach_inline_header', ['name' => $name])."\n\n".$text
                    );

                    continue;
                }

                if ($caps['documents']) {
                    $parts[] = Document::fromLocalPath($path, $name);

                    continue;
                }

                // Unreachable through the widget, which narrows the allowlist by
                // provider — but send() is a public Livewire method and this
                // service is public API, so the guard stays. A note rather than
                // silence: the model must be told the file did not arrive.
                $notes[] = trans('ptah::ui.ai_attach_note_unsupported', [
                    'name' => $name,
                    'provider' => $provider,
                ]);
            } catch (Throwable $e) {
                // One bad file must not cost the message the user wrote.
                Log::error('ptah: anexo de IA descartado neste turno.', [
                    'name' => $name,
                    'mime' => $mime,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                $notes[] = trans('ptah::ui.ai_attach_note_unreadable', ['name' => $name]);
            }
        }

        return ['parts' => $parts, 'notes' => $notes];
    }

    /**
     * Text of a text file, truncated with a marker.
     */
    public function readText(string $path): ?string
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        // A file the browser labelled text/* can still be in any encoding, and
        // invalid UTF-8 in the JSON payload to the provider is a hard error —
        // so convert rather than hope.
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', ['UTF-8', 'ISO-8859-1', 'Windows-1252']);
        }

        $limit = max(1000, (int) config('ptah.ai_agent.attachments.max_extracted_chars', 60000));

        // Truncation is MARKED, not silent: a model handed a fragment with no
        // sign that it is a fragment summarises it as the whole document.
        if (mb_strlen($raw) > $limit) {
            return mb_substr($raw, 0, $limit)."\n\n".trans('ptah::ui.ai_attach_truncated');
        }

        return $raw;
    }
}
