<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Services\AI\AiAttachmentService;
use Ptah\Tests\TestCase;
use RuntimeException;

/**
 * Which attachments a provider can actually receive.
 *
 * The failure this guards against is silent. A message part a provider's Prism
 * MessageMap does not handle is not serialised — the request succeeds, the model
 * answers about a document it never received, and nothing says so. A
 * confidently wrong answer is the worst thing this feature could produce, so the
 * package refuses the attachment instead of offering it.
 *
 * The list in DOCUMENT_PROVIDERS is a claim about someone else's code, so these
 * tests check it AGAINST that code rather than restating it. Both directions:
 * every provider on the list must have a DocumentMapper, and every provider off
 * it must not. Without the second half the list could quietly go stale in the
 * safe-looking direction — a provider gaining document support upstream would
 * keep being refused — and without the first half a provider losing it would
 * bring the silent drop back.
 */
class AiAttachmentCapabilityTest extends TestCase
{
    private const PRISM_PROVIDERS = __DIR__.'/../../../vendor/prism-php/prism/src/Providers';

    private function service(): AiAttachmentService
    {
        return $this->app->make(AiAttachmentService::class);
    }

    /**
     * Prism's provider directories, lowercased, as the package names them.
     *
     * @return array<string, string> slug => directory
     */
    private static function prismProviderDirs(): array
    {
        if (! is_dir(self::PRISM_PROVIDERS)) {
            throw new RuntimeException('Prism nao esta instalado onde este teste espera: '.self::PRISM_PROVIDERS);
        }

        $out = [];

        foreach ((array) scandir(self::PRISM_PROVIDERS) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = self::PRISM_PROVIDERS.'/'.$entry;

            if (is_dir($path)) {
                $out[strtolower((string) $entry)] = $path;
            }
        }

        if ($out === []) {
            throw new RuntimeException('Nenhum provedor encontrado no Prism — o teste perderia sentido.');
        }

        return $out;
    }

    /**
     * A provider "takes documents" when its message map has a DocumentMapper to
     * serialise the part with.
     */
    private static function prismMapsDocuments(string $dir): bool
    {
        return is_file($dir.'/Maps/DocumentMapper.php');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function documentProviderProvider(): array
    {
        return array_combine(
            AiAttachmentService::DOCUMENT_PROVIDERS,
            array_map(static fn (string $p): array => [$p], AiAttachmentService::DOCUMENT_PROVIDERS)
        );
    }

    #[Test]
    #[DataProvider('documentProviderProvider')]
    public function every_provider_on_the_list_really_maps_documents(string $provider): void
    {
        $dirs = self::prismProviderDirs();

        $this->assertArrayHasKey(
            $provider,
            $dirs,
            "`{$provider}` esta em DOCUMENT_PROVIDERS mas nao existe no Prism instalado."
        );

        $this->assertTrue(
            self::prismMapsDocuments($dirs[$provider]),
            "`{$provider}` esta em DOCUMENT_PROVIDERS e o Prism instalado nao tem DocumentMapper para ele — ".
            'o anexo seria descartado em silencio e o modelo responderia sobre um arquivo que nunca recebeu.'
        );
    }

    #[Test]
    public function no_provider_that_maps_documents_is_missing_from_the_list(): void
    {
        // The other direction. Without it, a provider that gains document
        // support upstream keeps being refused for no reason, and the list rots
        // in the direction that looks safe.
        $missing = [];

        foreach (self::prismProviderDirs() as $slug => $dir) {
            if (! self::prismMapsDocuments($dir)) {
                continue;
            }

            if (! in_array($slug, AiAttachmentService::DOCUMENT_PROVIDERS, true)) {
                $missing[] = $slug;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'Provedor(es) que o Prism instalado ja sabe mapear documento e que continuam recusados: '.
            implode(', ', $missing).'. Acrescente em AiAttachmentService::DOCUMENT_PROVIDERS.'
        );
    }

    #[Test]
    public function grok_is_on_the_blind_side_and_that_is_the_whole_point(): void
    {
        // Pinned explicitly because it is the provider the reporting host runs,
        // and the reason the refusal exists at all rather than a fallback.
        $dirs = self::prismProviderDirs();

        $this->assertArrayHasKey('xai', $dirs);
        $this->assertFalse(self::prismMapsDocuments($dirs['xai']));
        $this->assertFalse($this->service()->capabilities('xai')['documents']);
    }

    #[Test]
    public function images_work_everywhere(): void
    {
        // Every text provider in Prism maps an Image part, which is why a
        // pasted screenshot is offered unconditionally.
        foreach (['xai', 'openai', 'anthropic', 'groq', 'deepseek', 'ollama'] as $provider) {
            $this->assertTrue(
                $this->service()->capabilities($provider)['images'],
                "Imagem deveria funcionar em {$provider}."
            );
        }
    }

    #[Test]
    public function the_provider_name_is_matched_loosely(): void
    {
        // It comes from a database column an administrator typed.
        $this->assertTrue($this->service()->capabilities('  OpenAI  ')['documents']);
        $this->assertTrue($this->service()->capabilities('ANTHROPIC')['documents']);
    }

    #[Test]
    public function an_unknown_provider_is_treated_as_document_blind(): void
    {
        // The safe default: refuse rather than offer something that may vanish
        // in transit.
        $this->assertFalse($this->service()->capabilities('provedor-que-nao-existe')['documents']);
        $this->assertFalse($this->service()->capabilities('')['documents']);
    }

    #[Test]
    public function a_document_blind_provider_is_not_offered_pdf_or_docx(): void
    {
        config(['ptah.ai_agent.attachments.allowed_extensions' => ['png', 'pdf', 'docx', 'txt', 'csv']]);

        $allowed = $this->service()->allowedExtensions('xai');

        $this->assertContains('png', $allowed);
        $this->assertNotContains('pdf', $allowed, 'PDF nao pode ser oferecido onde ele seria descartado.');
        $this->assertNotContains('docx', $allowed);
    }

    #[Test]
    public function text_files_are_offered_even_there(): void
    {
        // Not a compromise: a .csv IS text, so sending it as text loses nothing
        // — there is no layout or page image to lose. Refusing it would be
        // worse for the user with no gain in honesty.
        config(['ptah.ai_agent.attachments.allowed_extensions' => ['pdf', 'txt', 'md', 'csv', 'json']]);

        $allowed = $this->service()->allowedExtensions('xai');

        foreach (['txt', 'md', 'csv', 'json'] as $ext) {
            $this->assertContains($ext, $allowed, "`{$ext}` e texto e deveria passar em qualquer provedor.");
        }
    }

    #[Test]
    public function a_document_capable_provider_is_offered_everything_configured(): void
    {
        config(['ptah.ai_agent.attachments.allowed_extensions' => ['png', 'pdf', 'docx', 'txt']]);

        $this->assertEqualsCanonicalizing(
            ['png', 'pdf', 'docx', 'txt'],
            $this->service()->allowedExtensions('openai')
        );
        $this->assertSame([], $this->service()->blockedExtensions('openai'));
    }

    #[Test]
    public function the_blocked_list_says_exactly_what_this_provider_refuses(): void
    {
        // It reaches the screen, so the refusal is legible before it happens —
        // and it is actionable, because the provider is chosen in the picker
        // right above.
        config(['ptah.ai_agent.attachments.allowed_extensions' => ['png', 'pdf', 'docx', 'txt']]);

        $this->assertEqualsCanonicalizing(['pdf', 'docx'], $this->service()->blockedExtensions('xai'));
    }

    #[Test]
    public function the_allowlist_tolerates_how_it_was_written(): void
    {
        config(['ptah.ai_agent.attachments.allowed_extensions' => ['.PNG', ' pdf ', '', 'TXT', 'png']]);

        $allowed = $this->service()->allowedExtensions('openai');

        $this->assertEqualsCanonicalizing(['png', 'pdf', 'txt'], $allowed);
    }
}
