<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Models\AiModelConfig;
use Ptah\Tests\TestCase;

/**
 * Attaching from the widget: the picker, the paste, the drop.
 *
 * The validation all lives on the server, and these tests are mostly about that
 * — because the client-side filter is a convenience that saves bandwidth and
 * tells the user early, and `$wire.upload` is a public call that does not have
 * to go through it. Extension, size and count are decided here.
 */
class AiChatAttachmentWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ptah.ai_agent.allow_guests' => true,
            'ptah.ai_agent.attachments.enabled' => true,
            'ptah.ai_agent.attachments.max_files' => 2,
            'ptah.ai_agent.attachments.max_size_kb' => 100,
            'ptah.ai_agent.attachments.allowed_extensions' => ['png', 'pdf', 'txt'],
        ]);
    }

    private function makeConfig(string $provider = 'openai'): AiModelConfig
    {
        return AiModelConfig::create([
            'name' => 'Config '.$provider,
            'provider' => $provider,
            'model' => 'modelo',
            'api_key' => 'sk-test',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    #[Test]
    public function an_accepted_file_lands_in_the_list(): void
    {
        $this->makeConfig();

        Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->create('notas.txt', 2, 'text/plain'))
            ->assertCount('attachments', 1)
            // The scratch slot is emptied so the next paste is not compared
            // against a stale value.
            ->assertSet('incoming', null)
            ->assertSet('errorMsg', '');
    }

    #[Test]
    public function files_accumulate_one_at_a_time(): void
    {
        // The reason for the scratch slot at all: paste and drop arrive one file
        // at a time, and uploadMultiple would replace the whole array.
        $this->makeConfig();

        Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->create('a.txt', 1, 'text/plain'))
            ->set('incoming', UploadedFile::fake()->create('b.txt', 1, 'text/plain'))
            ->assertCount('attachments', 2);
    }

    #[Test]
    public function the_count_limit_is_enforced_on_the_server(): void
    {
        $this->makeConfig();

        $component = Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->create('a.txt', 1, 'text/plain'))
            ->set('incoming', UploadedFile::fake()->create('b.txt', 1, 'text/plain'))
            ->set('incoming', UploadedFile::fake()->create('c.txt', 1, 'text/plain'));

        $component->assertCount('attachments', 2);
        $this->assertStringContainsString('2', (string) $component->get('errorMsg'));
    }

    #[Test]
    public function an_oversized_file_is_refused_and_named(): void
    {
        $this->makeConfig();

        $component = Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->create('enorme.txt', 500, 'text/plain'));

        $component->assertCount('attachments', 0);
        $this->assertStringContainsString('enorme.txt', (string) $component->get('errorMsg'));
    }

    #[Test]
    public function an_extension_outside_the_configuration_is_refused(): void
    {
        $this->makeConfig();

        $component = Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->create('script.exe', 1, 'application/octet-stream'));

        $component->assertCount('attachments', 0);
        $this->assertStringContainsString('script.exe', (string) $component->get('errorMsg'));
    }

    #[Test]
    public function a_pdf_is_refused_on_a_document_blind_provider_and_the_reason_says_so(): void
    {
        // The behaviour asked for: do not accept what would be dropped in
        // transit. And say WHY, because the provider is chosen in the picker
        // right above — the user can act on it.
        $this->makeConfig('xai');

        $component = Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->create('relatorio.pdf', 1, 'application/pdf'));

        $component->assertCount('attachments', 0);

        $error = (string) $component->get('errorMsg');

        $this->assertStringContainsString('relatorio.pdf', $error);
        $this->assertStringContainsString('xai', $error);
    }

    #[Test]
    public function the_same_pdf_is_accepted_on_a_document_capable_provider(): void
    {
        // The counterpart: the refusal above must be about the provider, not
        // about PDFs.
        $this->makeConfig('openai');

        Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->create('relatorio.pdf', 1, 'application/pdf'))
            ->assertCount('attachments', 1);
    }

    #[Test]
    public function an_image_is_accepted_on_a_document_blind_provider(): void
    {
        // Pasting a screenshot into Grok is the headline case.
        $this->makeConfig('xai');

        Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->image('print.png'))
            ->assertCount('attachments', 1);
    }

    #[Test]
    public function the_picker_only_offers_what_this_provider_receives(): void
    {
        $this->makeConfig('xai');

        $html = Livewire::test(AiChatWidget::class)->set('isOpen', true)->html();

        $this->assertStringContainsString('.png', $html);
        $this->assertStringNotContainsString('accept=".png,.pdf,.txt"', $html);
        // And it says out loud what is missing, before anyone tries.
        $this->assertStringContainsString('pdf', $html);
    }

    #[Test]
    public function removing_an_attachment_reindexes_the_list(): void
    {
        // The chips are drawn with the array index as the argument, so a hole
        // would make the next click remove the wrong file.
        $this->makeConfig();

        $component = Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->create('a.txt', 1, 'text/plain'))
            ->set('incoming', UploadedFile::fake()->create('b.txt', 1, 'text/plain'))
            ->call('removeAttachment', 0);

        $component->assertCount('attachments', 1);

        $remaining = $component->get('attachments');

        $this->assertArrayHasKey(0, $remaining, 'A lista precisa ser reindexada.');
        $this->assertSame('b.txt', $remaining[0]->getClientOriginalName());
    }

    #[Test]
    public function removing_an_index_that_is_not_there_does_nothing(): void
    {
        $this->makeConfig();

        Livewire::test(AiChatWidget::class)
            ->call('removeAttachment', 7)
            ->assertCount('attachments', 0)
            ->assertOk();
    }

    #[Test]
    public function an_attachment_alone_is_a_valid_message(): void
    {
        // "analyse this" is often just the file.
        $this->makeConfig();

        $component = Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->image('print.png'))
            ->call('send', '');

        $component->assertSet('loading', true);

        $messages = $component->get('messages');

        $this->assertCount(1, $messages);
        $this->assertSame(['print.png'], $messages[0]['attachments']);
    }

    #[Test]
    public function nothing_at_all_still_sends_nothing(): void
    {
        $this->makeConfig();

        Livewire::test(AiChatWidget::class)
            ->call('send', '')
            ->assertSet('loading', false)
            ->assertNotDispatched('ai-process-message');
    }

    #[Test]
    public function the_files_survive_until_the_second_request_reads_them(): void
    {
        // send() and processAiMessage() are separate requests. Clearing the
        // attachments in send() would empty them before they were ever sent.
        $this->makeConfig();

        Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->image('print.png'))
            ->call('send', 'olha isso')
            ->assertCount('attachments', 1);
    }

    #[Test]
    public function a_new_conversation_drops_the_pending_attachments(): void
    {
        $this->makeConfig();

        Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->image('print.png'))
            ->call('newConversation')
            ->assertCount('attachments', 0);
    }

    #[Test]
    public function attachments_disabled_by_configuration_offers_nothing(): void
    {
        config(['ptah.ai_agent.attachments.enabled' => false]);
        $this->makeConfig();

        $component = Livewire::test(AiChatWidget::class)->set('isOpen', true);

        $this->assertStringNotContainsString('bx-paperclip', $component->html());

        $component->set('incoming', UploadedFile::fake()->image('print.png'))
            ->assertCount('attachments', 0);
    }
}
