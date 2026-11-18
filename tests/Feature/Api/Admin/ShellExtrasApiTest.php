<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Admin;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Export;
use Ptah\Models\Notification;
use Ptah\Services\Notification\NotificationService;
use Ptah\Tests\TestCase;

class ShellExtrasUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];
}

/**
 * The notification bell and the exports panel through the API (1.59.0), for
 * the React and Vue stacks. The Livewire components are the engine — these
 * tests pin the contract and that their scoping holds through it.
 */
class ShellExtrasApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // tableExists() is memoized per process: one test's answer must not
        // leak into the next.
        NotificationService::forgetTableExistsCache();
    }

    protected function tearDown(): void
    {
        NotificationService::forgetTableExistsCache();

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.notifications.enabled', true);
        $app['config']->set('ptah.notifications.poll', '30s');
        $app['config']->set('auth.providers.users.model', ShellExtrasUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        // The opt-in ptah_notifications table.
        $this->loadMigrationsFrom(dirname(__DIR__, 4).'/database/migrations');
    }

    private function user(string $name = 'Ana'): ShellExtrasUser
    {
        return ShellExtrasUser::create([
            'name' => $name,
            'email' => strtolower($name).uniqid().'@example.test',
            'password' => bcrypt('secret'),
        ]);
    }

    private function notify(ShellExtrasUser $user, array $data = []): Notification
    {
        $notification = app(NotificationService::class)->push((int) $user->id, array_merge(['title' => 'Pedido aprovado'], $data));
        $this->assertNotNull($notification);

        return $notification;
    }

    private function export(ShellExtrasUser $user, array $data = []): Export
    {
        return Export::create(array_merge([
            'user_id' => $user->id,
            'model' => 'App\\Models\\Order',
            'route' => 'orders',
            'format' => 'excel',
            'status' => 'done',
            'payload' => [],
        ], $data));
    }

    // ── Access ──────────────────────────────────────────────────────────────

    #[Test]
    public function a_guest_is_refused_everywhere(): void
    {
        $this->getJson('/ptah/api/notifications')->assertUnauthorized();
        $this->postJson('/ptah/api/notifications/read-all')->assertUnauthorized();
        $this->postJson('/ptah/api/notifications/1/open')->assertUnauthorized();
        $this->postJson('/ptah/api/notifications/1/dismiss')->assertUnauthorized();
        $this->getJson('/ptah/api/exports')->assertUnauthorized();
        $this->deleteJson('/ptah/api/exports/1')->assertUnauthorized();
    }

    // ── Notification bell ───────────────────────────────────────────────────

    #[Test]
    public function the_bell_answers_the_badge_and_the_poll_without_the_list_while_closed(): void
    {
        $ana = $this->user();
        $this->notify($ana);
        $this->notify($ana, ['title' => 'Segunda']);
        $this->notify($this->user('Bia'));

        $this->actingAs($ana)->getJson('/ptah/api/notifications')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('unread', 2)
            ->assertJsonPath('poll_ms', 30000)
            ->assertJsonPath('items', null)
            ->assertJsonPath('view_all_url', null);
    }

    #[Test]
    public function the_open_dropdown_lists_only_the_users_own_notifications_whitelisted(): void
    {
        $ana = $this->user();
        $this->notify($ana, ['title' => 'Com link', 'body' => 'Corpo', 'url' => '/orders/1', 'action_label' => 'Abrir']);
        $this->notify($ana, ['title' => 'Link perigoso', 'url' => 'javascript:alert(1)']);
        $this->notify($this->user('Bia'), ['title' => 'Da Bia']);

        $response = $this->actingAs($ana)->getJson('/ptah/api/notifications?items=1')->assertOk();

        $this->assertCount(2, $response->json('items'));
        $items = collect($response->json('items'))->keyBy('title');
        // A dangerous scheme is neutralised, as the view does.
        $this->assertNull($items['Link perigoso']['url']);
        $this->assertSame('/orders/1', $items['Com link']['url']);
        $this->assertSame('Abrir', $items['Com link']['action_label']);
        $this->assertSame('Corpo', $items['Com link']['body']);
        $this->assertSame(
            ['id', 'type', 'title', 'body', 'icon', 'url', 'action_label', 'read', 'created_at', 'created_at_human'],
            array_keys($items['Com link']),
        );
        $this->assertArrayNotHasKey('user_id', $items['Com link']);
    }

    #[Test]
    public function polling_off_answers_a_null_interval(): void
    {
        config()->set('ptah.notifications.poll', 'none');

        $this->actingAs($this->user())->getJson('/ptah/api/notifications')->assertJsonPath('poll_ms', null);

        config()->set('ptah.notifications.poll', '1500ms');

        $this->actingAs($this->user('Bia'))->getJson('/ptah/api/notifications')->assertJsonPath('poll_ms', 1500);
    }

    #[Test]
    public function opening_a_notification_marks_it_read_and_answers_where_to_go(): void
    {
        $ana = $this->user();
        $item = $this->notify($ana, ['url' => '/orders/7']);

        $this->actingAs($ana)->postJson("/ptah/api/notifications/{$item->id}/open")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('redirect', '/orders/7')
            ->assertJsonPath('unread', 0);

        $this->assertNotNull($item->fresh()?->read_at);
    }

    #[Test]
    public function a_forged_id_of_another_users_notification_is_refused_and_left_untouched(): void
    {
        $ana = $this->user();
        $foreign = $this->notify($this->user('Bia'), ['url' => '/orders/9']);

        $this->actingAs($ana)->postJson("/ptah/api/notifications/{$foreign->id}/open")->assertNotFound();
        $this->actingAs($ana)->postJson("/ptah/api/notifications/{$foreign->id}/dismiss")->assertOk();

        $fresh = $foreign->fresh();
        $this->assertNull($fresh?->read_at);
        $this->assertNull($fresh?->dismissed_at);
    }

    #[Test]
    public function marking_all_read_clears_the_badge_and_confirms(): void
    {
        $ana = $this->user();
        $this->notify($ana);
        $this->notify($ana, ['title' => 'Outra']);

        $this->actingAs($ana)->postJson('/ptah/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread', 0)
            ->assertJsonPath('messages.0.text', trans_choice('ptah::ui.notif_marked_read', 2, ['count' => 2]))
            ->assertJsonPath('messages.0.level', 'success')
            ->assertJsonCount(2, 'items');

        $this->actingAs($ana)->postJson('/ptah/api/notifications/read-all')
            ->assertJsonPath('messages.0.text', trans('ptah::ui.notif_none_unread'));
    }

    #[Test]
    public function dismissing_hides_a_notification_from_the_dropdown(): void
    {
        $ana = $this->user();
        $keep = $this->notify($ana, ['title' => 'Fica']);
        $gone = $this->notify($ana, ['title' => 'Sai']);

        $this->actingAs($ana)->postJson("/ptah/api/notifications/{$gone->id}/dismiss")
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $keep->id);
    }

    #[Test]
    public function with_the_feature_off_the_bell_is_inert(): void
    {
        config()->set('ptah.notifications.enabled', false);
        $ana = $this->user();

        $this->actingAs($ana)->getJson('/ptah/api/notifications?items=1')
            ->assertOk()
            ->assertJsonPath('enabled', false)
            ->assertJsonPath('unread', 0)
            ->assertJsonPath('items', null);
        $this->actingAs($ana)->postJson('/ptah/api/notifications/read-all')->assertNotFound();
        $this->actingAs($ana)->postJson('/ptah/api/notifications/1/open')->assertNotFound();
        $this->actingAs($ana)->postJson('/ptah/api/notifications/1/dismiss')->assertNotFound();
    }

    #[Test]
    public function with_the_table_missing_the_bell_is_empty_not_broken(): void
    {
        Schema::drop('ptah_notifications');
        NotificationService::forgetTableExistsCache();

        $this->actingAs($this->user())->getJson('/ptah/api/notifications?items=1')
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('unread', 0)
            ->assertJsonPath('items', []);
    }

    // ── Exports panel ───────────────────────────────────────────────────────

    #[Test]
    public function the_panel_lists_only_the_users_own_exports_whitelisted(): void
    {
        $ana = $this->user();
        $done = $this->export($ana, ['rows' => 12, 'file_disk' => 'local', 'file_path' => 'ptah-exports/a.xlsx']);
        $expired = $this->export($ana, ['expires_at' => now()->subHour()]);
        $failed = $this->export($ana, ['status' => 'failed', 'error' => 'Sem memória']);
        $this->export($this->user('Bia'), ['model' => 'App\\Models\\Secret']);

        $response = $this->actingAs($ana)->getJson('/ptah/api/exports')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('has_pending', false)
            ->assertJsonPath('poll_ms', null);

        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertSame('Order', $rows[$done->id]['model']);
        $this->assertSame(12, $rows[$done->id]['rows']);
        $this->assertSame(route('ptah.export.file', ['export' => $done->id]), $rows[$done->id]['download_url']);
        $this->assertNull($rows[$expired->id]['download_url']);
        $this->assertTrue($rows[$expired->id]['expired']);
        $this->assertSame('Sem memória', $rows[$failed->id]['error']);
        $this->assertNull($rows[$failed->id]['download_url']);
        $this->assertSame(
            ['id', 'model', 'format', 'status', 'error', 'rows', 'created_at', 'created_at_display', 'expired', 'download_url'],
            array_keys($rows[$done->id]),
        );
        $this->assertStringNotContainsString('Secret', (string) $response->getContent());
        $this->assertStringNotContainsString('ptah-exports/a.xlsx', (string) $response->getContent());
    }

    #[Test]
    public function a_pending_export_asks_the_panel_to_poll(): void
    {
        $ana = $this->user();
        $this->export($ana, ['status' => 'processing']);

        $this->actingAs($ana)->getJson('/ptah/api/exports')
            ->assertJsonPath('has_pending', true)
            ->assertJsonPath('poll_ms', 5000);
    }

    #[Test]
    public function the_panel_paginates_ten_a_page(): void
    {
        $ana = $this->user();
        for ($i = 0; $i < 12; $i++) {
            $this->export($ana);
        }

        $this->actingAs($ana)->getJson('/ptah/api/exports?page=2')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonCount(2, 'data');

        // A page past the last one shows the last page.
        $this->actingAs($ana)->getJson('/ptah/api/exports?page=9')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function removing_an_export_deletes_its_file_and_row(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ptah-exports/a.xlsx', 'x');
        $ana = $this->user();
        $export = $this->export($ana, ['file_disk' => 'local', 'file_path' => 'ptah-exports/a.xlsx']);

        $this->actingAs($ana)->deleteJson("/ptah/api/exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('meta.total', 0);

        $this->assertNull($export->fresh());
        Storage::disk('local')->assertMissing('ptah-exports/a.xlsx');
    }

    #[Test]
    public function a_forged_id_of_another_users_export_is_refused_and_kept(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ptah-exports/b.xlsx', 'x');
        $foreign = $this->export($this->user('Bia'), ['file_disk' => 'local', 'file_path' => 'ptah-exports/b.xlsx']);

        $this->actingAs($this->user())->deleteJson("/ptah/api/exports/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->user('Caio'))->deleteJson('/ptah/api/exports/999999')->assertNotFound();

        $this->assertNotNull($foreign->fresh());
        Storage::disk('local')->assertExists('ptah-exports/b.xlsx');
    }
}
