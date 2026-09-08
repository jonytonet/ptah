<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiAttachmentService;
use Ptah\Tests\TestCase;

/**
 * Attachments on a host whose published config predates them.
 *
 * `mergeConfigFrom` is SHALLOW. A host that ran `vendor:publish` on
 * `config/ptah.php` owns the whole `ptah.ai_agent` array from that moment on, so
 * a NESTED key a later version of the package adds never reaches them —
 * `ptah.ai_agent.attachments` simply is not there.
 *
 * The first version of this feature read `allowed_extensions` with `[]` as the
 * fallback, which made "the key is absent" indistinguishable from "allow
 * nothing". The paperclip, the paste handler and the drop target all sit behind
 * the same condition, so the entire feature was invisible on every host with a
 * published config — reported as "nao achei como enviar documentos, nem o print
 * com Ctrl+V funcionou", on an app whose config was published long before.
 *
 * This is a trap this project has walked into before with a nested key, which is
 * why the test forces the harshest shape: the `attachments` block removed
 * entirely, exactly as a stale published config looks.
 */
class AiAttachmentStaleConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ptah.ai_agent.allow_guests' => true]);

        // The whole point: not an empty array, ABSENT — as a shallow merge
        // leaves it.
        $agent = (array) config('ptah.ai_agent');
        unset($agent['attachments']);
        config(['ptah.ai_agent' => $agent]);

        $this->assertNull(
            config('ptah.ai_agent.attachments'),
            'O arranjo do teste precisa que o bloco esteja realmente ausente.'
        );

        AiModelConfig::create([
            'name' => 'Grok',
            'provider' => 'xai',
            'model' => 'grok-2',
            'api_key' => 'sk-test',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    private function service(): AiAttachmentService
    {
        return $this->app->make(AiAttachmentService::class);
    }

    #[Test]
    public function the_feature_is_on_with_no_configuration_at_all(): void
    {
        $this->assertTrue($this->service()->enabled());
    }

    #[Test]
    public function the_defaults_come_from_the_package_not_from_an_empty_array(): void
    {
        $allowed = $this->service()->allowedExtensions('openai');

        $this->assertNotSame([], $allowed, 'Sem default no codigo, a lista some e a UI toda se esconde.');
        $this->assertContains('png', $allowed);
        $this->assertContains('pdf', $allowed);
        $this->assertContains('txt', $allowed);

        $this->assertSame(AiAttachmentService::DEFAULT_MAX_FILES, $this->service()->maxFiles());
        $this->assertSame(AiAttachmentService::DEFAULT_MAX_SIZE_KB, $this->service()->maxSizeKb());
    }

    #[Test]
    public function the_provider_narrowing_still_applies(): void
    {
        // The default list must not become a way around the capability rule.
        $allowed = $this->service()->allowedExtensions('xai');

        $this->assertContains('png', $allowed);
        $this->assertNotContains('pdf', $allowed);
        $this->assertEqualsCanonicalizing(['pdf', 'docx'], $this->service()->blockedExtensions('xai'));
    }

    #[Test]
    public function the_paperclip_and_the_paste_handler_reach_the_page(): void
    {
        // The three things the user could not find, all behind one condition.
        $html = Livewire::test(AiChatWidget::class)->set('isOpen', true)->html();

        $this->assertStringContainsString('bx-paperclip', $html, 'O clipe nao renderizou.');
        $this->assertStringContainsString('onPaste($event)', $html, 'O Ctrl+V nao esta ligado.');
        $this->assertStringContainsString('onDrop($event)', $html, 'O arraste nao esta ligado.');
    }

    #[Test]
    public function a_screenshot_can_actually_be_attached_on_grok(): void
    {
        // End to end on the reported configuration: the provider that takes no
        // documents, and the file type the user was trying to paste.
        Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->image('print.png'))
            ->assertCount('attachments', 1)
            ->assertSet('errorMsg', '');
    }

    #[Test]
    public function a_host_that_does_configure_the_block_is_still_obeyed(): void
    {
        // The counterpart: code defaults must not override an explicit choice.
        config(['ptah.ai_agent.attachments' => [
            'enabled' => true,
            'allowed_extensions' => ['png'],
            'max_files' => 1,
        ]]);

        $this->assertSame(['png'], $this->service()->allowedExtensions('openai'));
        $this->assertSame(1, $this->service()->maxFiles());
    }

    #[Test]
    public function a_host_can_still_turn_the_feature_off(): void
    {
        config(['ptah.ai_agent.attachments' => ['enabled' => false]]);

        $this->assertFalse($this->service()->enabled());

        $html = Livewire::test(AiChatWidget::class)->set('isOpen', true)->html();

        $this->assertStringNotContainsString('bx-paperclip', $html);
    }

    #[Test]
    public function an_empty_allowlist_is_honoured_when_it_is_written_on_purpose(): void
    {
        // An explicit `[]` means "allow nothing" and must keep meaning that —
        // the fix is about an ABSENT key, not about ignoring the host.
        config(['ptah.ai_agent.attachments' => ['allowed_extensions' => []]]);

        $this->assertSame([], $this->service()->allowedExtensions('openai'));
    }
}
