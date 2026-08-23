<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Notification\NotificationBell;
use Ptah\Services\Notification\NotificationService;

/**
 * FASE 3 of the notification hook plan: the ptah-notification-bell Livewire
 * component (badge, dropdown gating, openItem ownership/safeUrl, poll).
 */
class NotificationBellTest extends NotificationTestCase
{
    private function loginAs(int $id): void
    {
        /** @var class-string<Model> $userClass */
        $userClass = config('auth.providers.users.model');

        $userClass::forceCreate([
            'id' => $id,
            'name' => 'User '.$id,
            'email' => "user{$id}@test.com",
            'password' => bcrypt('secret'),
        ]);

        Auth::loginUsingId($id);
    }

    #[Test]
    public function the_badge_shows_the_unread_count_and_is_absent_at_zero(): void
    {
        $this->loginAs(1);
        app(NotificationService::class)->push(1, ['title' => 'Uma']);
        app(NotificationService::class)->push(1, ['title' => 'Duas']);

        Livewire::test(NotificationBell::class)
            ->assertSee('2');

        app(NotificationService::class)->markAllRead(1);

        $html = Livewire::test(NotificationBell::class)->html();
        $this->assertStringNotContainsString('bg-danger', $html);
    }

    #[Test]
    public function the_empty_state_is_shown_when_there_are_no_active_notifications(): void
    {
        $this->loginAs(1);

        $html = Livewire::test(NotificationBell::class)
            ->call('toggle')
            ->html();

        $this->assertStringContainsString(__('ptah::ui.notif_empty'), $html);
    }

    #[Test]
    public function a_closed_dropdown_never_runs_the_items_query(): void
    {
        $this->loginAs(1);
        app(NotificationService::class)->push(1, ['title' => 'Segredo Fechado']);

        $html = Livewire::test(NotificationBell::class)->html();

        $this->assertStringNotContainsString('Segredo Fechado', $html);
    }

    #[Test]
    public function opening_the_dropdown_runs_the_items_query_and_shows_the_title(): void
    {
        $this->loginAs(1);
        app(NotificationService::class)->push(1, ['title' => 'Visivel Aberto']);

        $html = Livewire::test(NotificationBell::class)
            ->call('toggle')
            ->html();

        $this->assertStringContainsString('Visivel Aberto', $html);
    }

    #[Test]
    public function open_item_marks_it_read_and_redirects_to_its_url(): void
    {
        $this->loginAs(1);
        $notification = app(NotificationService::class)->push(1, ['title' => 'x', 'url' => '/pets/42']);

        Livewire::test(NotificationBell::class)
            ->call('toggle')
            ->call('openItem', $notification->id)
            ->assertRedirect('/pets/42');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    #[Test]
    public function open_item_belonging_to_another_user_neither_marks_nor_redirects(): void
    {
        $this->loginAs(1);
        $foreign = app(NotificationService::class)->push(2, ['title' => 'x', 'url' => '/pets/42']);

        Livewire::test(NotificationBell::class)
            ->call('toggle')
            ->call('openItem', $foreign->id)
            ->assertNoRedirect();

        $this->assertNull($foreign->fresh()->read_at);
    }

    #[Test]
    public function a_malicious_url_never_becomes_an_href_or_a_redirect_target(): void
    {
        $this->loginAs(1);
        $notification = app(NotificationService::class)->push(1, [
            'title' => 'Perigosa',
            'url' => 'javascript:alert(1)',
        ]);

        $html = Livewire::test(NotificationBell::class)
            ->call('toggle')
            ->assertNoRedirect()
            ->html();

        $this->assertStringNotContainsString('javascript:alert(1)', $html);

        Livewire::test(NotificationBell::class)
            ->call('toggle')
            ->call('openItem', $notification->id)
            ->assertNoRedirect();
    }

    #[Test]
    public function wire_poll_is_present_with_a_valid_interval_and_absent_when_disabled(): void
    {
        $this->loginAs(1);

        config(['ptah.notifications.poll' => '60s']);
        $html = Livewire::test(NotificationBell::class)->html();
        $this->assertStringContainsString('wire:poll.60s', $html);

        config(['ptah.notifications.poll' => 'none']);
        $html = Livewire::test(NotificationBell::class)->html();
        $this->assertStringNotContainsString('wire:poll', $html);
    }

    #[Test]
    public function the_list_is_scoped_by_company(): void
    {
        $this->loginAs(1);
        app(NotificationService::class)->push(1, ['title' => 'Empresa 1', 'company_id' => 1]);
        app(NotificationService::class)->push(1, ['title' => 'Empresa 2', 'company_id' => 2]);

        session([config('ptah.permissions.company_session_key', 'ptah_company_id') => 1]);

        $html = Livewire::test(NotificationBell::class)
            ->call('toggle')
            ->html();

        $this->assertStringContainsString('Empresa 1', $html);
        $this->assertStringNotContainsString('Empresa 2', $html);
    }

    #[Test]
    public function rendering_without_the_table_migrated_does_not_explode(): void
    {
        $this->loginAs(1);

        // Drops the table WITHIN the RefreshDatabase transaction — rolled
        // back at tearDown() like every other write this test makes, so
        // later tests still see the table SchemaSetup created for them.
        Schema::dropIfExists('ptah_notifications');
        NotificationService::forgetTableExistsCache();

        Livewire::test(NotificationBell::class)
            ->call('toggle')
            ->assertSee(__('ptah::ui.notif_empty'));
    }

    #[Test]
    public function db_listen_confirms_the_dropdown_limit_query_only_runs_after_opening(): void
    {
        $this->loginAs(1);
        app(NotificationService::class)->push(1, ['title' => 'x']);

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        Livewire::test(NotificationBell::class);

        $limitedSelects = array_filter(
            $queries,
            fn ($sql) => str_contains($sql, 'ptah_notifications') && str_contains($sql, 'limit')
        );
        $this->assertCount(0, $limitedSelects, 'items() rodou com a dropdown fechada.');

        $queries = [];

        Livewire::test(NotificationBell::class)->call('toggle');

        $limitedSelects = array_filter(
            $queries,
            fn ($sql) => str_contains($sql, 'ptah_notifications') && str_contains($sql, 'limit')
        );
        $this->assertNotCount(0, $limitedSelects, 'items() nao rodou com a dropdown aberta.');
    }
}
