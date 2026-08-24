<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Notification;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Jobs\SendCrudNotificationJob;
use Ptah\Models\CrudConfig;
use Ptah\Models\Notification;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Services\Notification\CrudNotificationDispatcher;
use Ptah\Services\Notification\NotificationService;
use Ptah\Support\ModelKey;
use Ptah\Traits\SendsCrudNotifications;
use Throwable;

// ─── Stub models ─────────────────────────────────────────────────────────────
//
// Two identical tables/models (see tests/migrations/…_create_crud_notification_stubs_table.php)
// so the recursion-latch test can exercise two DISTINCT classes.

class CrudNotificationStubA extends Model
{
    use SendsCrudNotifications;

    protected $table = 'crud_notification_stub_a';

    protected $fillable = ['name', 'secret', 'company_id'];
}

class CrudNotificationStubB extends Model
{
    use SendsCrudNotifications;

    protected $table = 'crud_notification_stub_b';

    protected $fillable = ['name', 'secret', 'company_id'];
}

/**
 * FASE 3 of the config-driven CRUD notifications plan: CrudNotificationDispatcher
 * + SendsCrudNotifications wiring. Uses NotificationTestCase, which layers the
 * opt-in ptah_notifications migration on top of the base TestCase and enables
 * `ptah.notifications.enabled`.
 */
class CrudNotificationDispatcherTest extends NotificationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CrudNotificationDispatcher::forgetMemo();
    }

    protected function tearDown(): void
    {
        CrudNotificationDispatcher::forgetMemo();

        parent::tearDown();
    }

    /**
     * Creates a real row in the configured user model and logs it in.
     * `Auth::loginUsingId()` silently no-ops when the id does not resolve to
     * a real user — see NotificationBellTest::loginAs() for the same idiom.
     */
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

    /**
     * @param  array<int, array<string, mixed>>  $rules
     * @param  array<int, array<string, mixed>>  $cols
     */
    private function seedConfig(string $modelClass, array $rules, array $cols = []): CrudConfig
    {
        return CrudConfig::create([
            'model' => ModelKey::canonical($modelClass),
            'route' => '',
            'config' => [
                'cols' => $cols,
                'notifications' => ['rules' => $rules],
            ],
        ]);
    }

    private function defaultCols(): array
    {
        return [
            ['colsNomeFisico' => 'name'],
            ['colsNomeFisico' => 'secret', 'colsPermission' => 'stub.secret'],
        ];
    }

    // ── Gating ──────────────────────────────────────────────────────────────

    #[Test]
    public function config_without_the_notifications_key_dispatches_nothing(): void
    {
        Bus::fake([SendCrudNotificationJob::class]);

        CrudConfig::create([
            'model' => ModelKey::canonical(CrudNotificationStubA::class),
            'route' => '',
            'config' => ['cols' => $this->defaultCols()],
        ]);

        CrudNotificationStubA::create(['name' => 'Acme']);

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function an_empty_rules_array_dispatches_nothing(): void
    {
        Bus::fake([SendCrudNotificationJob::class]);

        $this->seedConfig(CrudNotificationStubA::class, [], $this->defaultCols());

        CrudNotificationStubA::create(['name' => 'Acme']);

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function the_module_being_off_dispatches_nothing_and_never_queries(): void
    {
        config(['ptah.notifications.enabled' => false]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'Novo %name%'],
        ], $this->defaultCols());

        $model = new CrudNotificationStubA(['name' => 'Acme']);

        DB::enableQueryLog();
        app(CrudNotificationDispatcher::class)->dispatch($model, 'created');
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $log, 'The dispatcher must not query anything while the module is disabled.');
    }

    // ── Event → job with resolved payload ────────────────────────────────────

    #[Test]
    public function created_dispatches_one_job_with_the_resolved_payload(): void
    {
        Bus::fake([SendCrudNotificationJob::class]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'Novo: %name% (#%id%)'],
        ], $this->defaultCols());

        $record = CrudNotificationStubA::create(['name' => 'Acme']);

        Bus::assertDispatchedTimes(SendCrudNotificationJob::class, 1);
        Bus::assertDispatched(SendCrudNotificationJob::class, fn (SendCrudNotificationJob $job) => $job->payload['title'] === "Novo: Acme (#{$record->id})"
            && $job->audience === 'staff'
        );
    }

    #[Test]
    public function updated_dispatches_one_job_with_the_resolved_payload(): void
    {
        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'updated', 'audience' => 'staff', 'title' => 'Atualizado: %name%'],
        ], $this->defaultCols());

        $record = CrudNotificationStubA::create(['name' => 'Acme']);

        Bus::fake([SendCrudNotificationJob::class]);
        $record->update(['name' => 'Acme LTDA']);

        Bus::assertDispatchedTimes(SendCrudNotificationJob::class, 1);
        Bus::assertDispatched(
            SendCrudNotificationJob::class,
            fn (SendCrudNotificationJob $job) => $job->payload['title'] === 'Atualizado: Acme LTDA'
        );
    }

    #[Test]
    public function deleted_dispatches_one_job_with_the_payload_resolved_from_the_already_removed_row(): void
    {
        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'deleted', 'audience' => 'staff', 'title' => 'Removido: %name%'],
        ], $this->defaultCols());

        $record = CrudNotificationStubA::create(['name' => 'Acme']);

        Bus::fake([SendCrudNotificationJob::class]);
        $record->delete();

        $this->assertDatabaseMissing('crud_notification_stub_a', ['id' => $record->id]);
        Bus::assertDispatchedTimes(SendCrudNotificationJob::class, 1);
        Bus::assertDispatched(
            SendCrudNotificationJob::class,
            fn (SendCrudNotificationJob $job) => $job->payload['title'] === 'Removido: Acme'
        );
    }

    // ── company_id resolution ─────────────────────────────────────────────────

    #[Test]
    public function the_records_own_company_id_wins_over_the_session(): void
    {
        session(['ptah_company_id' => 999]);

        Bus::fake([SendCrudNotificationJob::class]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'x'],
        ], $this->defaultCols());

        CrudNotificationStubA::create(['name' => 'Acme', 'company_id' => 55]);

        Bus::assertDispatched(
            SendCrudNotificationJob::class,
            fn (SendCrudNotificationJob $job) => $job->payload['company_id'] === 55
        );
    }

    // ── Placeholder allowlist ─────────────────────────────────────────────────

    #[Test]
    public function a_column_restricted_by_colspermission_resolves_to_an_empty_string_even_for_a_master_actor(): void
    {
        $this->loginAs(1);

        Bus::fake([SendCrudNotificationJob::class]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'x', 'body' => 'secret=%secret%', 'notifySelf' => true],
        ], $this->defaultCols());

        CrudNotificationStubA::create(['name' => 'Acme', 'secret' => 'do-not-leak']);

        Bus::assertDispatched(
            SendCrudNotificationJob::class,
            fn (SendCrudNotificationJob $job) => $job->payload['body'] === 'secret='
        );
    }

    #[Test]
    public function a_restricted_primary_key_is_not_smuggled_in_through_the_pk_allowance(): void
    {
        // Bypass real, pego em revisao: a PK era readicionada a allowlist SEM
        // checar a propria tag. Um admin que marca colsPermission na coluna
        // `id` ainda veria %id% resolvido para todo destinatario — a restricao
        // vencia em toda tela e perdia exatamente aqui, onde o texto fica
        // gravado no banco.
        $this->loginAs(1);

        Bus::fake([SendCrudNotificationJob::class]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'x', 'url' => '/stub/%id%', 'body' => 'id=%id%', 'notifySelf' => true],
        ], [
            ['colsNomeFisico' => 'name'],
            ['colsNomeFisico' => 'id', 'colsPermission' => 'stub.identifier'],
        ]);

        CrudNotificationStubA::create(['name' => 'Acme', 'secret' => 'x']);

        Bus::assertDispatched(
            SendCrudNotificationJob::class,
            fn (SendCrudNotificationJob $job) => $job->payload['body'] === 'id='
                && $job->payload['url'] === '/stub/'
        );
    }

    #[Test]
    public function an_unrestricted_primary_key_still_resolves_even_without_a_column_entry(): void
    {
        // O outro lado do contrato: PK sem entrada em cols (ou sem tag) segue
        // disponivel, senao /rota/%id% deixaria de funcionar para todo mundo.
        $this->loginAs(1);

        Bus::fake([SendCrudNotificationJob::class]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'x', 'url' => '/stub/%id%', 'notifySelf' => true],
        ], [['colsNomeFisico' => 'name']]);

        $row = CrudNotificationStubA::create(['name' => 'Acme', 'secret' => 'x']);

        Bus::assertDispatched(
            SendCrudNotificationJob::class,
            fn (SendCrudNotificationJob $job) => $job->payload['url'] === '/stub/'.$row->id
        );
    }

    #[Test]
    public function an_unknown_placeholder_resolves_to_an_empty_string(): void
    {
        Bus::fake([SendCrudNotificationJob::class]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'x', 'body' => 'v=%does_not_exist%'],
        ], $this->defaultCols());

        CrudNotificationStubA::create(['name' => 'Acme']);

        Bus::assertDispatched(
            SendCrudNotificationJob::class,
            fn (SendCrudNotificationJob $job) => $job->payload['body'] === 'v='
        );
    }

    // ── Title truncation ───────────────────────────────────────────────────────

    #[Test]
    public function a_title_over_180_chars_is_truncated_and_the_insert_succeeds(): void
    {
        $longTitle = str_repeat('A', 200);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => $longTitle],
        ], $this->defaultCols());

        Role::create(['name' => 'Staff', 'is_active' => true]);
        UserRole::create(['user_id' => 1, 'role_id' => Role::first()->id, 'is_active' => true]);

        CrudNotificationStubA::create(['name' => 'Acme']);

        $notification = Notification::query()->first();

        $this->assertNotNull($notification);
        $this->assertSame(180, strlen($notification->title));
        $this->assertSame(str_repeat('A', 180), $notification->title);
    }

    // ── Author exclusion ───────────────────────────────────────────────────────

    #[Test]
    public function the_actor_is_excluded_by_default(): void
    {
        $role = Role::create(['name' => 'Staff', 'is_active' => true]);
        UserRole::create(['user_id' => 100, 'role_id' => $role->id, 'is_active' => true]);
        UserRole::create(['user_id' => 200, 'role_id' => $role->id, 'is_active' => true]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'x'],
        ], $this->defaultCols());

        $this->loginAs(100);
        CrudNotificationStubA::create(['name' => 'Acme']);

        $this->assertFalse(Notification::query()->where('user_id', 100)->exists());
        $this->assertTrue(Notification::query()->where('user_id', 200)->exists());
    }

    #[Test]
    public function notify_self_true_includes_the_actor(): void
    {
        $role = Role::create(['name' => 'Staff', 'is_active' => true]);
        UserRole::create(['user_id' => 100, 'role_id' => $role->id, 'is_active' => true]);
        UserRole::create(['user_id' => 200, 'role_id' => $role->id, 'is_active' => true]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'x', 'notifySelf' => true],
        ], $this->defaultCols());

        $this->loginAs(100);
        CrudNotificationStubA::create(['name' => 'Acme']);

        $this->assertTrue(Notification::query()->where('user_id', 100)->exists());
        $this->assertTrue(Notification::query()->where('user_id', 200)->exists());
    }

    #[Test]
    public function without_an_authenticated_actor_nobody_is_excluded(): void
    {
        $role = Role::create(['name' => 'Staff', 'is_active' => true]);
        UserRole::create(['user_id' => 100, 'role_id' => $role->id, 'is_active' => true]);
        UserRole::create(['user_id' => 200, 'role_id' => $role->id, 'is_active' => true]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'x'],
        ], $this->defaultCols());

        CrudNotificationStubA::create(['name' => 'Acme']);

        $this->assertTrue(Notification::query()->where('user_id', 100)->exists());
        $this->assertTrue(Notification::query()->where('user_id', 200)->exists());
    }

    // ── Audience routing ─────────────────────────────────────────────────────

    #[Test]
    public function audience_user_routes_through_to_user(): void
    {
        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'user', 'audienceValue' => '42', 'title' => 'x'],
        ], $this->defaultCols());

        CrudNotificationStubA::create(['name' => 'Acme']);

        $this->assertTrue(Notification::query()->where('user_id', 42)->exists());
        $this->assertSame(1, Notification::query()->count());
    }

    #[Test]
    public function audience_role_routes_through_to_role(): void
    {
        $role = Role::create(['name' => 'Financeiro', 'is_active' => true]);
        UserRole::create(['user_id' => 7, 'role_id' => $role->id, 'is_active' => true]);
        // Different role — must not be notified.
        $other = Role::create(['name' => 'Vendas', 'is_active' => true]);
        UserRole::create(['user_id' => 8, 'role_id' => $other->id, 'is_active' => true]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'role', 'audienceValue' => 'Financeiro', 'title' => 'x'],
        ], $this->defaultCols());

        CrudNotificationStubA::create(['name' => 'Acme']);

        $this->assertTrue(Notification::query()->where('user_id', 7)->exists());
        $this->assertFalse(Notification::query()->where('user_id', 8)->exists());
    }

    #[Test]
    public function audience_staff_routes_through_to_all_with_only_staff(): void
    {
        $role = Role::create(['name' => 'Qualquer', 'is_active' => true]);
        UserRole::create(['user_id' => 11, 'role_id' => $role->id, 'is_active' => true]);
        UserRole::create(['user_id' => 12, 'role_id' => $role->id, 'is_active' => true]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'x'],
        ], $this->defaultCols());

        CrudNotificationStubA::create(['name' => 'Acme']);

        $this->assertTrue(Notification::query()->where('user_id', 11)->exists());
        $this->assertTrue(Notification::query()->where('user_id', 12)->exists());
    }

    // ── Bulk delete inside a transaction ─────────────────────────────────────

    #[Test]
    public function a_bulk_delete_inside_a_transaction_only_inserts_notifications_after_commit(): void
    {
        $role = Role::create(['name' => 'Staff', 'is_active' => true]);
        UserRole::create(['user_id' => 1, 'role_id' => $role->id, 'is_active' => true]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'deleted', 'audience' => 'staff', 'title' => 'Removido: %name%'],
        ], $this->defaultCols());

        $a = CrudNotificationStubA::create(['name' => 'A']);
        $b = CrudNotificationStubA::create(['name' => 'B']);

        DB::transaction(function () use ($a, $b) {
            $a->delete();
            $b->delete();

            // The job is deferred via afterCommit — nothing must be inserted
            // while the transaction is still open.
            $this->assertSame(0, Notification::query()->count());
        });

        $this->assertSame(2, Notification::query()->count());
    }

    #[Test]
    public function a_rolled_back_transaction_results_in_zero_notifications(): void
    {
        $role = Role::create(['name' => 'Staff', 'is_active' => true]);
        UserRole::create(['user_id' => 1, 'role_id' => $role->id, 'is_active' => true]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'deleted', 'audience' => 'staff', 'title' => 'Removido: %name%'],
        ], $this->defaultCols());

        $a = CrudNotificationStubA::create(['name' => 'A']);

        try {
            DB::transaction(function () use ($a) {
                $a->delete();

                throw new \RuntimeException('force rollback');
            });
        } catch (Throwable) {
            // expected
        }

        $this->assertSame(0, Notification::query()->count());
    }

    // ── Recursion latch ───────────────────────────────────────────────────────

    #[Test]
    public function a_nested_model_creation_triggered_while_dispatching_does_not_recurse(): void
    {
        $role = Role::create(['name' => 'Staff', 'is_active' => true]);
        UserRole::create(['user_id' => 1, 'role_id' => $role->id, 'is_active' => true]);

        $this->seedConfig(CrudNotificationStubA::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'A criado', 'notifySelf' => true],
        ], $this->defaultCols());
        $this->seedConfig(CrudNotificationStubB::class, [
            ['event' => 'created', 'audience' => 'staff', 'title' => 'B criado', 'notifySelf' => true],
        ], $this->defaultCols());

        // Simulates a side effect (e.g. an observer) that creates a SECOND
        // notifiable model WHILE the dispatcher is still processing the
        // outer event — the latch must suppress the nested dispatch.
        $this->app->bind(NotificationService::class, function () {
            return new class extends NotificationService
            {
                public function toAll(array $data, ?int $companyId = null, bool $onlyStaff = true, ?int $exceptUserId = null): int
                {
                    if (! CrudNotificationStubB::query()->exists()) {
                        CrudNotificationStubB::create(['name' => 'nested']);
                    }

                    return parent::toAll($data, $companyId, $onlyStaff, $exceptUserId);
                }
            };
        });

        CrudNotificationStubA::create(['name' => 'Acme']);

        // The nested StubB row was created, but its OWN notification rule
        // must never have fired — only StubA's outer event produced a
        // notification, proving the latch suppressed the reentrant call.
        $this->assertTrue(CrudNotificationStubB::query()->exists());
        $this->assertSame(1, Notification::query()->count());
        $this->assertSame('A criado', Notification::query()->first()->title);
    }
}
