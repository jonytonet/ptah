<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\AiConversation;
use Ptah\Models\AiModelConfig;
use Ptah\Services\AI\AiChatService;
use Ptah\Tests\TestCase;

/**
 * What actually goes on the wire when a file is attached.
 *
 * Reported after the feature shipped: an image was attached, the chip showed on
 * screen, and Grok answered "Não, eu não consigo ver imagens anexadas." A model
 * that receives an image it cannot read normally ERRORS; a model that receives
 * no image answers exactly like that. So the question was whether the image
 * reached the request at all.
 *
 * Everything up to that point was already covered and passing — `toPrismParts`
 * builds an Image part, the widget validates and stores the upload, the handoff
 * between `send()` and `processAiMessage()` carries a path that exists. Those
 * tests all stop one layer short of the only thing that settles it: the bytes
 * Prism puts in the HTTP body.
 *
 * So this test intercepts the real client. It is the layer that was missing, and
 * it is the layer where a silently-dropped message part would live.
 */
class AiAttachmentWirePayloadTest extends TestCase
{
    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    private function png(string $name = 'image.png'): array
    {
        $bytes = (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
        );

        $path = sys_get_temp_dir().'/ptah-wire-'.uniqid().'-'.$name;
        file_put_contents($path, $bytes);
        $this->temp[] = $path;

        return ['path' => $path, 'name' => $name, 'mime' => 'image/png'];
    }

    private function config(string $provider, string $model): AiModelConfig
    {
        return AiModelConfig::create([
            'name' => 'Cfg '.$provider,
            'provider' => $provider,
            'model' => $model,
            'api_key' => 'sk-test',
            'max_tokens' => 1024,
            'temperature' => 0.7,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    /**
     * Uma resposta por FORMATO, casada por URL.
     *
     * A primeira versao deste arranjo usava um unico corpo com as chaves de
     * todos os provedores misturadas, e tres casos estouraram no parser do
     * Prism — nao no produto. Cada familia devolve o que ela sabe ler, senao o
     * teste falha por motivo errado e esconde o que estava medindo.
     */
    private function fakeOk(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'id' => 'msg_1',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'claude',
                'content' => [['type' => 'text', 'text' => 'ok']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ]),
            // xai e openrouter falam chat/completions.
            '*' => Http::response([
                'id' => 'x',
                'model' => 'm',
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => 'ok'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]),
        ]);
    }

    private function bodyOfLastRequest(): string
    {
        $recorded = Http::recorded();

        $this->assertNotEmpty($recorded, 'Nenhuma requisicao capturada — o fake nao interceptou o cliente do Prism.');

        [$request] = $recorded[count($recorded) - 1];

        return (string) $request->body();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function providerProvider(): array
    {
        return [
            // The one that was reported.
            'xai' => ['xai', 'grok-2-vision-1212'],
            // openrouter rather than openai: both take documents, and
            // Provider::OpenAI posts to the Responses API, whose reply shape
            // this fake would also have to imitate. The point here is the
            // request, not the parsing of the answer.
            'openrouter' => ['openrouter', 'openai/gpt-4o'],
            'anthropic' => ['anthropic', 'claude-sonnet-4-20250514'],
        ];
    }

    #[Test]
    #[DataProvider('providerProvider')]
    public function an_attached_image_reaches_the_request_body(string $provider, string $model): void
    {
        $this->config($provider, $model);
        $this->fakeOk();

        config(['ptah.ai_agent.allow_guests' => true]);

        $this->app->make(AiChatService::class)->send(
            'consegue ver a imagem?',
            'sessao-de-teste',
            null,
            null,
            null,
            [$this->png()]
        );

        $body = $this->bodyOfLastRequest();

        // The base64 of the PNG, however the provider chose to wrap it. Asserted
        // on the payload rather than on a provider-specific key, so this test
        // does not have to know each mapper's shape — only that the bytes went.
        $this->assertStringContainsString(
            'iVBORw0KGgo',
            $body,
            "A imagem NAO foi para o corpo da requisicao do provedor {$provider}. ".
            'O modelo responderia sobre uma imagem que nunca recebeu.'
        );

        // And the question travelled with it, in the same message.
        $this->assertStringContainsString('consegue ver a imagem?', $body);

        // And the manifest, which is what makes a model without vision answer
        // "I was told image.png is attached but I do not process images"
        // instead of a flat "I cannot see images" — the second is
        // indistinguishable, for whoever reads it, from an attachment that got
        // lost on the way, and the two causes call for opposite actions.
        $this->assertStringContainsString('image.png', $body);
    }

    #[Test]
    public function a_text_attachment_reaches_the_body_on_a_document_blind_provider(): void
    {
        $this->config('xai', 'grok-2');
        $this->fakeOk();
        config(['ptah.ai_agent.allow_guests' => true]);

        $path = sys_get_temp_dir().'/ptah-wire-'.uniqid().'-notas.txt';
        file_put_contents($path, 'PEDIDO-4711 esta atrasado');
        $this->temp[] = $path;

        $this->app->make(AiChatService::class)->send(
            'o que diz o arquivo?',
            'sessao-de-teste',
            null,
            null,
            null,
            [['path' => $path, 'name' => 'notas.txt', 'mime' => 'text/plain']]
        );

        $body = $this->bodyOfLastRequest();

        $this->assertStringContainsString('PEDIDO-4711 esta atrasado', $body);
        $this->assertStringContainsString('notas.txt', $body);
    }

    #[Test]
    public function a_pdf_on_a_document_capable_provider_reaches_the_body(): void
    {
        $this->config('anthropic', 'claude-sonnet-4-20250514');
        $this->fakeOk();
        config(['ptah.ai_agent.allow_guests' => true]);

        $path = sys_get_temp_dir().'/ptah-wire-'.uniqid().'-doc.pdf';
        file_put_contents($path, "%PDF-1.4\nconteudo de teste\n%%EOF");
        $this->temp[] = $path;

        $this->app->make(AiChatService::class)->send(
            'resuma',
            'sessao-de-teste',
            null,
            null,
            null,
            [['path' => $path, 'name' => 'doc.pdf', 'mime' => 'application/pdf']]
        );

        $body = $this->bodyOfLastRequest();

        // O base64 do ARQUIVO INTEIRO. Uma versao anterior comparava
        // base64_encode('%PDF') — os quatro primeiros bytes codificados
        // isoladamente — que nao e prefixo do base64 do arquivo por causa do
        // alinhamento de 3 em 3 bytes. Falhava com o corpo correto, e a
        // asserticao espelho no teste seguinte passava a vazio pelo mesmo
        // motivo.
        $this->assertStringContainsString(
            base64_encode((string) file_get_contents($path)),
            $body,
            'O PDF nao foi no corpo, e este provedor aceita documento.'
        );
        // Sem asserticao sobre o mime: o JSON escapa a barra
        // (`application\/pdf`), entao procurar a forma nao escapada falha com o
        // corpo correto — e a forma NEGATIVA passaria a vazio pelo mesmo
        // motivo. O base64 acima ja prova que o documento foi.
        $this->assertStringContainsString('doc.pdf', $body);
    }

    #[Test]
    public function a_pdf_on_a_document_blind_provider_sends_the_note_instead_of_silence(): void
    {
        // The service is public API and `send()` is a public Livewire method, so
        // this path is reachable even though the widget narrows the allowlist.
        // The model must be TOLD, or it answers about content it never got —
        // which is exactly the shape of the reported symptom.
        $this->config('xai', 'grok-2');
        $this->fakeOk();
        config(['ptah.ai_agent.allow_guests' => true]);

        $path = sys_get_temp_dir().'/ptah-wire-'.uniqid().'-doc.pdf';
        file_put_contents($path, '%PDF-1.4');
        $this->temp[] = $path;

        $this->app->make(AiChatService::class)->send(
            'resuma',
            'sessao-de-teste',
            null,
            null,
            null,
            [['path' => $path, 'name' => 'doc.pdf', 'mime' => 'application/pdf']]
        );

        $body = $this->bodyOfLastRequest();

        $this->assertStringNotContainsString(
            base64_encode((string) file_get_contents($path)),
            $body,
            'O PDF foi para um provedor que o descartaria em silencio.'
        );
        $this->assertStringContainsString('doc.pdf', $body, 'A nota tem de nomear o arquivo que nao foi.');
    }

    #[Test]
    public function the_attachment_name_is_kept_in_the_stored_conversation(): void
    {
        // So a reopened conversation shows what was attached — the files
        // themselves were temporary and are gone.
        $this->config('xai', 'grok-2-vision-1212');
        $this->fakeOk();
        config(['ptah.ai_agent.allow_guests' => true]);

        $result = $this->app->make(AiChatService::class)->send(
            'olha isso',
            'sessao-de-teste',
            null,
            null,
            null,
            [$this->png('captura.png')]
        );

        $conversation = AiConversation::find($result['conversationId']);

        $this->assertNotNull($conversation);

        $userMessage = collect($conversation->messages)->firstWhere('role', 'user');

        $this->assertSame(['captura.png'], $userMessage['attachments'] ?? null);
    }

    #[Test]
    public function no_attachment_means_no_extra_content_parts(): void
    {
        // The counterpart: the plumbing must not add anything to an ordinary
        // message, which is every message.
        $this->config('xai', 'grok-2');
        $this->fakeOk();
        config(['ptah.ai_agent.allow_guests' => true]);

        $this->app->make(AiChatService::class)->send('so texto', 'sessao-de-teste');

        $body = $this->bodyOfLastRequest();

        $this->assertStringContainsString('so texto', $body);
        $this->assertStringNotContainsString('image_url', $body);
        $this->assertStringNotContainsString('OBSERVA', $body);
    }
}
