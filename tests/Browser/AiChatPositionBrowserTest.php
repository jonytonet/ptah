<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Auth\User as AuthUser;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\AiConversation;
use Ptah\Models\AiModelConfig;

/**
 * 1.59.2 — the assistant's chat on the Livewire layout, reported from
 * production: open across screens, but the list jumped back to its first
 * message; and a reload brought the LATEST conversation back, not the one the
 * person was reading from the history. Now the conversation on screen and the
 * reading position (per conversation) are kept for the tab — the same rule as
 * the React and Vue stacks.
 */
class AiChatPositionBrowserTest extends DuskTestCase
{
    private const EMAIL = 'dusk-ai-chat@example.test';

    private const LIST = "document.querySelector('[x-ref=msgList]')";

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('ptah.modules.ai_agent', true);
        $app['config']->set('ptah.forge.spa_navigation', true);

        // Each request of the Dusk server starts from an empty :memory: DB.
        $app->booted(function (Application $app) {
            $this->ensureChatFixtures();
        });
    }

    private function ensureChatFixtures(): void
    {
        if (AuthUser::query()->where('email', self::EMAIL)->exists()) {
            return;
        }

        $user = new AuthUser;
        $user->name = 'Ana';
        $user->email = self::EMAIL;
        $user->password = bcrypt('secret');
        $user->save();

        AiModelConfig::create([
            'name' => 'Dusk', 'provider' => 'openai', 'model' => 'gpt-4o-mini', 'api_key' => 'sk-dusk',
            'max_tokens' => 100, 'temperature' => 0.2, 'is_active' => true, 'is_default' => true,
        ]);

        $long = [];
        for ($i = 1; $i <= 20; $i++) {
            $long[] = ['role' => 'user', 'content' => "Pergunta {$i}"];
            $long[] = ['role' => 'assistant', 'content' => "Resposta numero {$i}, com texto suficiente para ocupar uma linha inteira do painel."];
        }

        // The long one is older; the latest is short.
        $older = AiConversation::create(['user_id' => $user->id, 'session_id' => 'dusk-long', 'title' => 'Conversa longa', 'messages' => $long]);
        $older->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();
        AiConversation::create(['user_id' => $user->id, 'session_id' => 'dusk-short', 'title' => 'Conversa curta', 'messages' => [
            ['role' => 'user', 'content' => 'oi'], ['role' => 'assistant', 'content' => 'Ola!'],
        ]]);
    }

    private function scrollTop(Browser $b): int
    {
        return (int) $b->script('const l = '.self::LIST.'; return l ? Math.round(l.scrollTop) : -1;')[0];
    }

    #[Test]
    public function the_chat_reopens_the_conversation_being_read_where_the_reader_was(): void
    {
        $this->browse(function (Browser $b) {
            $b->resize(1440, 900)->loginAs(self::EMAIL)->visit('/dusk-test/other');
            $b->waitUntil("window.Livewire && Livewire.all().some(c => c.name === 'ptah-ai-chat-widget')", 30);
            $b->pause(500);

            // Without a kept conversation: the latest (short) one.
            $b->script("sessionStorage.clear(); sessionStorage.setItem('ptah:ai:open', '1');");
            $b->refresh();
            $b->waitUntil(self::LIST, 30)->pause(1500);
            $this->assertStringContainsString('Ola!', $b->script('return '.self::LIST.'.innerText;')[0]);
            $b->waitUntil("sessionStorage.getItem('ptah:ai:conversation') !== null", 15);
            $short = $b->script("return sessionStorage.getItem('ptah:ai:conversation');")[0];

            // The person picked the long one from the history; the page settles on it.
            $b->script("Livewire.all().find(c => c.name === 'ptah-ai-chat-widget').\$wire.loadConversation(Livewire.all().find(c => c.name === 'ptah-ai-chat-widget').\$wire.conversationId - 1);");
            $b->waitUntil(self::LIST.' && '.self::LIST.'.scrollHeight > '.self::LIST.'.clientHeight + 100', 30)->pause(800);
            $long = $b->script("return sessionStorage.getItem('ptah:ai:conversation');")[0];
            $this->assertNotSame($short, $long, 'Picking a conversation keeps it for the tab.');

            // Reading an older message.
            $b->script('const l = '.self::LIST."; l.scrollTop = 120; l.dispatchEvent(new Event('scroll'));");
            $b->pause(300);
            $this->assertSame($long.':120', $b->script("return sessionStorage.getItem('ptah:ai:scroll');")[0]);

            // In-place navigation: the persisted chat keeps its position.
            $b->script("window.__marker = 42; window.Livewire.navigate('/dusk-test/persist');");
            $b->waitUntil("window.location.pathname === '/dusk-test/persist' && !document.documentElement.hasAttribute('data-ptah-swapping')", 30)->pause(800);
            $this->assertSame(42, $b->script('return window.__marker === undefined ? null : window.__marker;')[0]);
            $this->assertSame(120, $this->scrollTop($b), 'The chat jumped across the navigation.');

            // F5: the same conversation, at the same place.
            $b->refresh();
            $b->waitUntil(self::LIST.' && '.self::LIST.'.scrollHeight > '.self::LIST.'.clientHeight + 100', 30)->pause(1500);
            $this->assertSame($long, $b->script("return sessionStorage.getItem('ptah:ai:conversation');")[0]);
            $this->assertSame(120, $this->scrollTop($b), 'A reload did not reopen where the reader was.');

            // At the end: a reload opens at the end.
            $b->script('const l = '.self::LIST."; l.scrollTop = l.scrollHeight; l.dispatchEvent(new Event('scroll'));");
            $b->pause(300);
            $b->refresh();
            $b->waitUntil(self::LIST.' && '.self::LIST.'.scrollHeight > '.self::LIST.'.clientHeight + 100', 30)->pause(1500);
            $this->assertTrue((bool) $b->script('const l = '.self::LIST.'; return l.scrollHeight - l.scrollTop - l.clientHeight < 30;')[0]);

            $severe = array_values(array_filter($b->driver->manage()->getLog('browser'), fn (array $e) => $e['level'] === 'SEVERE' && ! str_contains($e['message'], 'favicon.ico')));
            $this->assertSame([], array_map(fn (array $e) => $e['message'], $severe));
        });
    }
}
