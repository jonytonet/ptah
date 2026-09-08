<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiChatService;
use Ptah\Services\AI\AiProviderConfigService;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Tests\TestCase;

/**
 * The hand-off between the two requests.
 *
 * `send()` and `processAiMessage()` are separate Livewire requests: the first
 * puts the user's line on screen immediately, the second waits on the model. The
 * uploaded files live in a public property between them, and if they did not
 * survive that boundary the service would be called with an empty list — which
 * produces NO note either, since the notes are built from the files. The model
 * would get a question about a file it never received and answer confidently
 * about content it invented.
 *
 * That is exactly the shape of a report this feature produced, so the boundary
 * gets its own test. Both branches, because the streaming one is what a provider
 * with a Stream handler actually takes.
 */
class AiAttachmentHandoffTest extends TestCase
{
    /** @var array<int, array{via: string, files: array<int, array<string, mixed>>}> */
    public static array $seen = [];

    /**
     * Recorded INSIDE the call, with the file's existence checked there.
     *
     * Checking afterwards proves nothing: `processAiMessage` deletes the
     * temporary files in its `finally`, and the first version of this test
     * failed for that reason alone — the file had existed at the right moment.
     *
     * @param  array<int, array{path: string, name: string, mime: string}>  $attachments
     */
    public static function record(string $via, array $attachments): void
    {
        self::$seen[] = [
            'via' => $via,
            'files' => array_map(fn (array $a): array => $a + ['existe' => is_file($a['path'])], $attachments),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['ptah.ai_agent.allow_guests' => true]);

        AiModelConfig::create([
            'name' => 'Grok', 'provider' => 'xai', 'model' => 'grok-2-vision-1212',
            'api_key' => 'sk-test', 'max_tokens' => 1024, 'temperature' => 0.7,
            'is_active' => true, 'is_default' => true,
        ]);

        self::$seen = [];

        $stub = new class($this->app->make(AiProviderConfigService::class), $this->app->make(AiToolRegistry::class)) extends AiChatService
        {
            public function send(string $message, string $sessionId, ?int $userId = null, ?int $conversationId = null, ?int $configId = null, array $attachments = []): array
            {
                AiAttachmentHandoffTest::record('send', $attachments);

                return ['text' => 'ok', 'conversationId' => 1];
            }

            public function stream(string $message, string $sessionId, ?int $userId = null, ?int $conversationId = null, ?callable $onDelta = null, ?int $configId = null, array $attachments = []): array
            {
                AiAttachmentHandoffTest::record('stream', $attachments);

                return ['text' => 'ok', 'conversationId' => 1];
            }

            public function supportsStreaming(?int $configId = null): bool
            {
                return (bool) config('ptah.ai_agent.stream', true);
            }
        };

        $this->app->instance(AiChatService::class, $stub);
    }

    private function attachAndSend(): void
    {
        $component = Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->image('image.png'))
            ->call('send', 'consegue ver a imagem?');

        $component->call('processAiMessage', 'consegue ver a imagem?', null);
    }

    #[Test]
    public function the_attachment_survives_to_the_non_streaming_call(): void
    {
        config(['ptah.ai_agent.stream' => false]);

        $this->attachAndSend();

        $this->assertCount(1, self::$seen, 'O servico nao foi chamado.');
        $this->assertSame('send', self::$seen[0]['via']);
        $this->assertCount(
            1,
            self::$seen[0]['files'],
            'O anexo NAO chegou ao servico: a lista veio vazia, e sem ela nem existe nota avisando o modelo.'
        );

        $file = self::$seen[0]['files'][0];

        $this->assertSame('image.png', $file['name']);
        $this->assertTrue($file['existe'], 'O caminho chegou mas o arquivo nao existia na hora da chamada.');
        $this->assertStringStartsWith('image/', $file['mime']);
    }

    #[Test]
    public function the_attachment_survives_to_the_streaming_call(): void
    {
        // The branch a provider with a Stream handler takes, which is xAI's.
        config(['ptah.ai_agent.stream' => true]);

        $this->attachAndSend();

        $this->assertCount(1, self::$seen);
        $this->assertSame('stream', self::$seen[0]['via']);
        $this->assertCount(1, self::$seen[0]['files'], 'No caminho de streaming o anexo nao chegou.');
        $this->assertTrue(self::$seen[0]['files'][0]['existe']);
    }

    #[Test]
    public function the_temporary_files_are_gone_once_the_turn_is_over(): void
    {
        // They were read; leaving them costs disk and would make the NEXT
        // message carry the same file without anyone asking.
        config(['ptah.ai_agent.stream' => false]);

        $component = Livewire::test(AiChatWidget::class)
            ->set('incoming', UploadedFile::fake()->image('image.png'))
            ->call('send', 'olha');

        $component->call('processAiMessage', 'olha', null);

        $component->assertCount('attachments', 0);

        $path = self::$seen[0]['files'][0]['path'];

        $this->assertFileDoesNotExist($path);
    }
}
