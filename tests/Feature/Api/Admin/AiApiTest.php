<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Admin;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Ptah\Contracts\AiConfirmableTool;
use Ptah\Models\AiConversation;
use Ptah\Models\AiModelConfig;
use Ptah\Support\AI\AiPreview;
use Ptah\Tests\Support\ActsAsPtahUser;
use Ptah\Tests\TestCase;

class AiApiUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];
}

/** A confirmable tool for the preview-card flow: the payload never leaves the server. */
final class AiApiStockFixTool implements AiConfirmableTool
{
    /** @var list<array<string, mixed>> */
    public static array $commits = [];

    public function name(): string
    {
        return 'stockFix';
    }

    public function description(): string
    {
        return 'Fixes the stock of two products.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): array
    {
        throw new \LogicException('execute() must never run for a confirmable tool.');
    }

    public function preview(array $arguments): AiPreview
    {
        return new AiPreview(
            summary: '2 produtos vao ser corrigidos.',
            rows: [['id' => 5, 'qty' => 10], ['id' => 6, 'qty' => 0]],
            payload: ['ids' => [5, 6], 'secret' => 'payload-fica-no-servidor'],
            columns: ['id' => 'ID', 'qty' => 'Qtd'],
        );
    }

    public function commit(array $payload): array
    {
        self::$commits[] = $payload;

        return ['message' => 'Corrigi 2 produtos.'];
    }
}

/**
 * The AI agent module through the API (1.59.0): the provider configuration
 * screen and the chat widget. The Livewire components are the engine — these
 * tests pin the contract, that the key never leaves the server, and that the
 * guards hold. No provider is called: Prism is faked as in tests/Feature/AI.
 */
class AiApiTest extends TestCase
{
    use ActsAsPtahUser;

    private const KEY = 'sk-live-segredo-que-nao-sai-1234567890';

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web', 'auth']);
        $app['config']->set('ptah.modules.ai_agent', true);
        $app['config']->set('ptah.ai_agent.stream', false);
        $app['config']->set('auth.providers.users.model', AiApiUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        AiApiStockFixTool::$commits = [];
    }

    private function signIn(bool $master = true): AiApiUser
    {
        $user = AiApiUser::forceCreate(['name' => 'Ana', 'email' => 'ana'.uniqid().'@example.test', 'password' => 'x']);
        $this->actAsMaster($master);
        $this->actingAs($user);

        return $user;
    }

    private function provider(array $attributes = []): AiModelConfig
    {
        return AiModelConfig::create($attributes + [
            'name' => 'Principal',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'api_key' => self::KEY,
            'max_tokens' => 256,
            'temperature' => 0.5,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    private function fakeAnswer(string ...$texts): void
    {
        Prism::fake(array_map(fn (string $text) => TextResponseFake::make()->withText($text)->withUsage(new Usage(2, 4)), $texts));
    }

    // ── Provider configurations ─────────────────────────────────────────────

    #[Test]
    public function the_list_is_whitelisted_and_never_carries_a_key(): void
    {
        $this->signIn();
        $this->provider(['notes' => 'Conta da empresa']);
        $this->provider(['name' => 'Local', 'provider' => 'ollama', 'model' => 'llama3', 'api_key' => '', 'is_default' => false, 'is_active' => false]);

        $response = $this->getJson('/ptah/api/admin/ai/models')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.name', 'Local')
            ->assertJsonPath('data.0.provider_label', 'Ollama (Local)')
            ->assertJsonPath('data.1.notes', 'Conta da empresa')
            ->assertJsonPath('state.sort', 'name')
            ->assertJsonPath('providers.0', ['value' => 'openai', 'label' => 'OpenAI']);

        $this->assertSame(['id', 'name', 'provider', 'provider_label', 'model', 'notes', 'is_active', 'is_default'], array_keys($response->json('data.0')));
        $this->assertStringNotContainsString(self::KEY, $response->getContent());

        $this->getJson('/ptah/api/admin/ai/models?direction=desc')->assertJsonPath('data.0.name', 'Principal');
        $this->getJson('/ptah/api/admin/ai/models?search=llama')->assertJsonPath('meta.total', 1);
    }

    #[Test]
    public function the_edit_form_answers_the_key_empty(): void
    {
        $this->signIn();
        $config = $this->provider(['api_endpoint' => 'https://proxy.example.test/v1']);

        $response = $this->getJson("/ptah/api/admin/ai/models/{$config->id}")
            ->assertOk()
            ->assertJsonPath('form.api_key', '')
            ->assertJsonPath('form.api_endpoint', 'https://proxy.example.test/v1')
            ->assertJsonPath('form.temperature', '0.50')
            ->assertJsonPath('form.is_default', true);

        $this->assertStringNotContainsString(self::KEY, $response->getContent());
        $this->getJson('/ptah/api/admin/ai/models/999')->assertNotFound();
    }

    #[Test]
    public function a_forged_sort_column_is_refused(): void
    {
        $this->signIn();

        $this->getJson('/ptah/api/admin/ai/models?sort=api_key')->assertStatus(422)->assertJsonValidationErrors(['sort']);
        $this->getJson('/ptah/api/admin/ai/models?direction=up')->assertStatus(422)->assertJsonValidationErrors(['direction']);
    }

    #[Test]
    public function a_configuration_is_created_with_the_screens_rules_and_the_key_is_encrypted(): void
    {
        $this->signIn();

        $this->postJson('/ptah/api/admin/ai/models', ['name' => '', 'provider' => 'openai', 'model' => 'gpt-4o'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'api_key']);

        // A provider outside the screen's list is refused.
        $this->postJson('/ptah/api/admin/ai/models', ['name' => 'X', 'provider' => 'evil', 'model' => 'm', 'api_key' => 'k'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);

        // The OpenAI-compatible alias needs its endpoint; a wrong type is named.
        $this->postJson('/ptah/api/admin/ai/models', ['name' => 'X', 'provider' => 'openai_compatible', 'model' => 'm', 'api_key' => 'k'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['api_endpoint']);
        $this->postJson('/ptah/api/admin/ai/models', ['name' => 'X', 'provider' => 'openai', 'model' => 'm', 'api_key' => 'k', 'max_tokens' => 'muitos'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_tokens']);

        // Ollama needs no key.
        $this->postJson('/ptah/api/admin/ai/models', ['name' => 'Local', 'provider' => 'ollama', 'model' => 'llama3'])->assertOk();

        $this->postJson('/ptah/api/admin/ai/models', [
            'name' => 'Claude',
            'provider' => 'anthropic',
            'model' => ' claude-sonnet ',
            'api_key' => ' '.self::KEY.' ',
            'max_tokens' => 2048,
            'temperature' => '0.3',
            'is_default' => true,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('messages.0.text', __('ptah::ui.ai_config_created'));

        $config = AiModelConfig::where('name', 'Claude')->firstOrFail();
        $this->assertSame('claude-sonnet', $config->model);
        $this->assertSame(self::KEY, $config->api_key);
        $this->assertTrue($config->is_default);
        // At rest the key is ciphertext (the model's `encrypted` cast).
        $raw = (string) DB::table('ptah_ai_model_configs')->where('id', $config->id)->value('api_key');
        $this->assertStringNotContainsString(self::KEY, $raw);
    }

    #[Test]
    public function an_update_with_a_blank_key_keeps_the_stored_one(): void
    {
        $this->signIn();
        $first = $this->provider();
        $second = $this->provider(['name' => 'Reserva', 'is_default' => false]);

        $this->putJson("/ptah/api/admin/ai/models/{$second->id}", ['name' => 'Reserva 2', 'api_key' => '', 'is_default' => true])
            ->assertOk()
            ->assertJsonPath('messages.0.text', __('ptah::ui.ai_config_updated'));

        $second->refresh();
        $this->assertSame('Reserva 2', $second->name);
        $this->assertSame(self::KEY, $second->api_key);
        $this->assertTrue($second->is_default);
        $this->assertFalse($first->refresh()->is_default);

        $this->putJson("/ptah/api/admin/ai/models/{$second->id}", ['api_key' => 'sk-nova'])->assertOk();
        $this->assertSame('sk-nova', $second->refresh()->api_key);
    }

    #[Test]
    public function a_configuration_is_made_default_and_deleted(): void
    {
        $this->signIn();
        $first = $this->provider();
        $second = $this->provider(['name' => 'Reserva', 'is_default' => false]);

        $this->postJson("/ptah/api/admin/ai/models/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('messages.0.text', __('ptah::ui.ai_config_set_default_ok'));
        $this->assertTrue($second->refresh()->is_default);
        $this->assertFalse($first->refresh()->is_default);

        $this->deleteJson("/ptah/api/admin/ai/models/{$first->id}")
            ->assertOk()
            ->assertJsonPath('messages.0.text', __('ptah::ui.ai_config_deleted'));
        $this->assertSoftDeleted('ptah_ai_model_configs', ['id' => $first->id]);

        // The screen reports a missing record in its error banner.
        $this->deleteJson('/ptah/api/admin/ai/models/999')->assertStatus(422)->assertJsonPath('ok', false);
    }

    #[Test]
    public function only_who_may_manage_ai_config_reaches_the_configuration_screen(): void
    {
        $config = $this->provider();

        // A guest: the route's `auth`.
        $this->getJson('/ptah/api/admin/ai/models')->assertUnauthorized();

        // Signed in without ai.config (and not master): the component's lock.
        $this->signIn(master: false);
        $this->actAsUserWhoCan(false);
        $this->getJson('/ptah/api/admin/ai/models')->assertForbidden();
        $this->getJson("/ptah/api/admin/ai/models/{$config->id}")->assertForbidden();
        $this->postJson('/ptah/api/admin/ai/models', ['name' => 'X', 'provider' => 'openai', 'model' => 'm', 'api_key' => 'k'])->assertForbidden();
        $this->putJson("/ptah/api/admin/ai/models/{$config->id}", ['api_key' => 'sk-roubada'])->assertForbidden();
        $this->postJson("/ptah/api/admin/ai/models/{$config->id}/default")->assertForbidden();
        $this->deleteJson("/ptah/api/admin/ai/models/{$config->id}")->assertForbidden();
        $this->assertSame(self::KEY, $config->refresh()->api_key);
        $this->assertSame(1, AiModelConfig::count());

        // `ai.config` read is the grant, without being master.
        $this->actAsUserWhoCan(true);
        $this->getJson('/ptah/api/admin/ai/models')->assertOk();
    }

    // ── Chat ────────────────────────────────────────────────────────────────

    #[Test]
    public function the_chat_is_refused_to_a_guest_and_absent_without_a_provider(): void
    {
        $this->getJson('/ptah/api/admin/ai/chat')->assertUnauthorized();
        $this->postJson('/ptah/api/admin/ai/chat/messages', ['message' => 'oi'])->assertUnauthorized();

        // Any signed-in user may chat — the ai.config lock is not the chat's.
        $this->signIn(master: false);
        $this->actAsUserWhoCan(false);
        $this->getJson('/ptah/api/admin/ai/chat')->assertOk()->assertExactJson(['available' => false]);
        $this->postJson('/ptah/api/admin/ai/chat/messages', ['message' => 'oi'])->assertNotFound();
    }

    #[Test]
    public function the_chat_state_lists_the_providers_without_their_keys(): void
    {
        $this->signIn(master: false);
        $this->actAsUserWhoCan(false);
        $default = $this->provider();
        $this->provider(['name' => 'Claude', 'provider' => 'anthropic', 'model' => 'claude', 'is_default' => false]);
        $this->provider(['name' => 'Desligado', 'is_default' => false, 'is_active' => false]);

        $response = $this->getJson('/ptah/api/admin/ai/chat')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('conversation_id', null)
            ->assertJsonPath('messages', [])
            ->assertJsonCount(2, 'providers')
            ->assertJsonPath('providers.0.name', 'Principal')
            ->assertJsonPath('selected_config_id', $default->id)
            ->assertJsonPath('history', true)
            ->assertJsonPath('attachments.enabled', true)
            ->assertJsonPath('attachments.max_files', 4);

        $this->assertSame(['id', 'name', 'provider', 'model', 'is_default'], array_keys($response->json('providers.0')));
        $this->assertContains('pdf', $response->json('attachments.extensions'));
        $this->assertStringNotContainsString(self::KEY, $response->getContent());

        // Ollama takes no documents: the picker narrows the formats.
        $local = $this->provider(['name' => 'Local', 'provider' => 'ollama', 'model' => 'llama3', 'api_key' => '', 'is_default' => false]);
        $narrowed = $this->getJson("/ptah/api/admin/ai/chat?config_id={$local->id}")->assertJsonPath('selected_config_id', $local->id);
        $this->assertNotContains('pdf', $narrowed->json('attachments.extensions'));
        $this->assertContains('pdf', $narrowed->json('attachments.blocked'));
    }

    #[Test]
    public function a_message_is_answered_and_the_conversation_continues_by_its_id(): void
    {
        $user = $this->signIn(master: false);
        $this->provider();
        $this->fakeAnswer('**Ola**, tudo bem?', 'Segunda resposta');

        $first = $this->postJson('/ptah/api/admin/ai/chat/messages', ['message' => '  Oi  '])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('messages.0', ['role' => 'user', 'content' => 'Oi', 'attachments' => []])
            ->assertJsonPath('messages.1.role', 'assistant')
            ->assertJsonPath('messages.1.content', '**Ola**, tudo bem?')
            ->assertJsonPath('conversations.0.title', 'Oi');
        $this->assertStringContainsString('<strong>Ola</strong>', (string) $first->json('messages.1.html'));
        $conversationId = (int) $first->json('conversation_id');
        $this->assertSame($user->id, (int) AiConversation::findOrFail($conversationId)->user_id);

        $this->postJson('/ptah/api/admin/ai/chat/messages', ['message' => 'E agora?', 'conversation_id' => $conversationId])
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversationId)
            ->assertJsonCount(4, 'messages')
            ->assertJsonPath('messages.3.content', 'Segunda resposta');
        $this->assertSame(1, AiConversation::count());

        // On open, the widget shows the latest conversation.
        $this->getJson('/ptah/api/admin/ai/chat')->assertJsonPath('conversation_id', $conversationId)->assertJsonCount(4, 'messages');
    }

    #[Test]
    public function the_answer_streams_as_server_sent_events(): void
    {
        config(['ptah.ai_agent.stream' => true]);
        $this->signIn(master: false);
        $this->provider();
        $this->fakeAnswer('Resposta em *stream*');

        $response = $this->post('/ptah/api/admin/ai/chat/messages', ['message' => 'Oi'], ['Accept' => 'text/event-stream']);
        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', (string) $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString("event: delta\ndata: ", $body);
        $this->assertStringContainsString('<em>stream</em>', $body);

        preg_match('/event: done\ndata: (.+)\n\n/', $body, $done);
        $this->assertNotEmpty($done);
        $answer = json_decode($done[1], true);
        $this->assertTrue($answer['ok']);
        $this->assertSame(200, $answer['status']);
        $this->assertSame('Resposta em *stream*', $answer['messages'][1]['content']);
    }

    #[Test]
    public function a_refused_turn_is_plain_json_even_when_streaming_was_asked(): void
    {
        config(['ptah.ai_agent.max_message_chars' => 10]);
        $this->signIn(master: false);
        $this->provider();

        $this->postJson('/ptah/api/admin/ai/chat/messages', ['message' => str_repeat('a', 11), 'stream' => true])
            ->assertStatus(422)
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('error', __('ptah::ui.ai_message_too_long', ['max' => 10]));
        $this->postJson('/ptah/api/admin/ai/chat/messages', ['message' => '   '])
            ->assertStatus(422)
            ->assertJsonPath('accepted', false);
        $this->assertSame(0, AiConversation::count());
    }

    #[Test]
    public function the_rate_limit_is_reported_in_the_answer(): void
    {
        config(['ptah.ai_agent.rate_limit' => 1]);
        $this->signIn(master: false);
        $this->provider();
        $this->fakeAnswer('Um', 'Dois');

        $this->postJson('/ptah/api/admin/ai/chat/messages', ['message' => 'Primeira'])->assertOk();
        $this->postJson('/ptah/api/admin/ai/chat/messages', ['message' => 'Segunda'])
            ->assertStatus(422)
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('error', __('ptah::ui.ai_widget_rate_limit'));
    }

    #[Test]
    public function another_users_conversation_cannot_be_read_nor_continued(): void
    {
        $this->provider();
        $owner = AiApiUser::forceCreate(['name' => 'Bia', 'email' => 'bia@example.test', 'password' => 'x']);
        $theirs = AiConversation::create(['user_id' => $owner->id, 'session_id' => 's', 'title' => 'Segredo', 'messages' => [['role' => 'user', 'content' => 'dado sigiloso']], 'tokens_used' => 0]);

        $this->signIn(master: false);
        $this->fakeAnswer('nao deveria');

        $this->getJson("/ptah/api/admin/ai/chat/conversations/{$theirs->id}")->assertNotFound();
        $this->postJson('/ptah/api/admin/ai/chat/messages', ['message' => 'Oi', 'conversation_id' => $theirs->id])->assertNotFound();
        $this->postJson('/ptah/api/admin/ai/chat/confirmations', ['conversation_id' => $theirs->id, 'token' => str_repeat('a', 32), 'confirm' => true])->assertNotFound();
        $this->assertCount(1, $theirs->refresh()->messages);
        $this->assertSame(1, AiConversation::count());
    }

    #[Test]
    public function the_history_lists_opens_and_starts_conversations(): void
    {
        $user = $this->signIn(master: false);
        $this->provider();
        for ($i = 1; $i <= 7; $i++) {
            AiConversation::create(['user_id' => $user->id, 'session_id' => 's', 'title' => "Conversa {$i}", 'messages' => [['role' => 'user', 'content' => "pergunta {$i}"], ['role' => 'assistant', 'content' => "resposta {$i}", 'tools' => [['name' => 'x', 'result' => 'interno']]]], 'tokens_used' => 0]);
        }
        $one = AiConversation::where('title', 'Conversa 1')->firstOrFail();

        $this->getJson('/ptah/api/admin/ai/chat/conversations')->assertOk()->assertJsonCount(5, 'conversations')->assertJsonPath('limit', 5);
        $this->getJson('/ptah/api/admin/ai/chat/conversations?limit=10')->assertJsonCount(7, 'conversations')->assertJsonPath('limit', 10);
        // The component's own ceiling.
        $this->getJson('/ptah/api/admin/ai/chat/conversations?limit=500')->assertJsonPath('limit', 100);

        $opened = $this->getJson("/ptah/api/admin/ai/chat/conversations/{$one->id}")
            ->assertOk()
            ->assertJsonPath('conversation_id', $one->id)
            ->assertJsonPath('messages.1.content', 'resposta 1');
        // Tool records are for the model, not for the screen.
        $this->assertStringNotContainsString('interno', $opened->getContent());

        $this->postJson('/ptah/api/admin/ai/chat/conversations')->assertOk()->assertExactJson(['conversation_id' => null, 'messages' => []]);
    }

    #[Test]
    public function an_attachment_goes_with_the_message_and_a_refused_one_stops_the_turn(): void
    {
        $this->signIn(master: false);
        $local = $this->provider(['provider' => 'ollama', 'api_key' => '']);
        $this->fakeAnswer('Li o arquivo.');

        // Ollama takes no PDF: refused by the widget's own upload check, before any provider call.
        $this->post('/ptah/api/admin/ai/chat/messages', [
            'message' => 'Analisa',
            'attachments' => [UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('error', __('ptah::ui.ai_attach_provider_no_docs', ['name' => 'nota.pdf', 'provider' => 'ollama']));
        $this->assertSame(0, AiConversation::count());

        $this->post('/ptah/api/admin/ai/chat/messages', [
            'message' => '',
            'config_id' => $local->id,
            'attachments' => [UploadedFile::fake()->createWithContent('lista.txt', "a\nb")],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('messages.0.attachments', ['lista.txt'])
            ->assertJsonPath('messages.1.content', 'Li o arquivo.');
    }

    #[Test]
    public function a_preview_card_is_confirmed_once_through_the_api(): void
    {
        config(['ptah.ai_agent.commands' => ['corrige-estoque' => ['tool' => AiApiStockFixTool::class, 'arguments' => [], 'description' => 'Corrige estoque']]]);
        $this->signIn(master: false);
        $this->provider();
        $this->fakeAnswer('Revise e confirme.', 'ok');

        $this->getJson('/ptah/api/admin/ai/chat')->assertJsonPath('commands', [['name' => 'corrige-estoque', 'description' => 'Corrige estoque']]);

        $turn = $this->postJson('/ptah/api/admin/ai/chat/messages', ['message' => '/corrige-estoque'])->assertOk();
        $card = $turn->json('messages.1.confirmations.0');
        $this->assertSame('pending', $card['status']);
        $this->assertSame([['key' => 'id', 'label' => 'ID'], ['key' => 'qty', 'label' => 'Qtd']], $card['columns']);
        $this->assertSame([['5', '10'], ['6', '0']], $card['rows']);
        $this->assertStringNotContainsString('payload-fica-no-servidor', $turn->getContent());
        $conversationId = (int) $turn->json('conversation_id');

        $this->postJson('/ptah/api/admin/ai/chat/confirmations', ['conversation_id' => $conversationId, 'token' => $card['token']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['confirm']);

        $this->postJson('/ptah/api/admin/ai/chat/confirmations', ['conversation_id' => $conversationId, 'token' => $card['token'], 'confirm' => true])
            ->assertOk()
            ->assertJsonPath('messages.1.confirmations.0.status', 'confirmed')
            ->assertJsonPath('messages.2.content', 'Corrigi 2 produtos.');
        $this->assertCount(1, AiApiStockFixTool::$commits);

        // A second click writes nothing.
        $this->postJson('/ptah/api/admin/ai/chat/confirmations', ['conversation_id' => $conversationId, 'token' => $card['token'], 'confirm' => true]);
        $this->assertCount(1, AiApiStockFixTool::$commits);
    }
}
