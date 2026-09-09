<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Exceptions\CrudHookAbort;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Livewire\BaseCrud\CrudConfig as CrudConfigEditor;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;
use RuntimeException;

class HookAbortStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * Hook classes for the tests, in a namespace the config allows.
 */
class AbortingHooks
{
    /**
     * The reported case: a guard that refuses the save.
     */
    public function beforeCreate(array &$data, ?Model $record, object $component): void
    {
        throw new CrudHookAbort('A senha temporária não pôde ser gerada.');
    }

    /**
     * An ordinary failure — swallowed unless the hook is declared critical.
     */
    public function beforeCreateBoom(array &$data, ?Model $record, object $component): void
    {
        throw new RuntimeException('cofre indisponível');
    }

    public function afterCreateBoom(array &$data, ?Model $record, object $component): void
    {
        throw new CrudHookAbort('o e-mail de boas-vindas falhou');
    }

    public function beforeUpdateBoom(array &$data, ?Model $record, object $component): void
    {
        throw new CrudHookAbort('o hash da senha seria regravado');
    }

    public function beforeCreateInvalid(array &$data, ?Model $record, object $component): void
    {
        throw ValidationException::withMessages(['name' => 'Este nome já existe no cofre.']);
    }

    /**
     * The one that must keep working: a side effect that fails and is ignored.
     */
    public function afterCreateNotify(array &$data, ?Model $record, object $component): void
    {
        throw new RuntimeException('serviço de notificação fora do ar');
    }
}

/**
 * A lifecycle hook that fails no longer has to be silent.
 *
 * `executeDynamicHook()` caught `\Throwable`, logged it, and let the save
 * continue. That is right for a hook that sends a notification or writes an
 * audit line — one flaky side effect must not take the form down. It is wrong
 * for a hook that is a BARRIER, and the reporter's own two cases show why:
 *
 *   a `beforeUpdate` that stops a password hash from being re-hashed
 *   a `beforeCreate` that generates a temporary password
 *
 * The first fails and the record saves without its guard. The second fails and
 * the record saves with an EMPTY password. Both write the row, both report
 * success, and both leave nothing behind but a log line — a silent failure in
 * exactly the place that exists to prevent one.
 *
 * It was not a bug: it was a decision, stated in the code. What was missing was
 * a way to say "not this one". There are now three:
 *
 *   1. the hook throws `CrudHookAbort` — never swallowed, no config needed
 *   2. the hook throws `ValidationException` — Laravel's own "reject this
 *      input", which swallowing can never be right for
 *   3. the config declares the hook critical, for a hook whose code you
 *      cannot change: `"lifecycleHooksCritical": {"beforeCreate": true}`
 *
 * The default is unchanged, and the first test here pins that on purpose: a
 * silent-by-default hook is a deliberate contract, and hosts depend on it.
 *
 * ── `before*` and `after*` abort differently ────────────────────────────
 *
 * A `before*` hook runs before anything is written, so aborting leaves nothing
 * behind. An `after*` hook runs after the row is committed and there is no
 * transaction around the save, so the record STAYS. Two different messages,
 * because "error saving" would be a lie about a record that exists.
 */
class CrudHookAbortTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Hook classes must live under an allowed namespace — the guard that
        // stops a crafted config from instantiating an arbitrary class.
        config()->set('ptah.crud.hook_namespaces', [__NAMESPACE__]);

        Log::spy();
    }

    /**
     * @param  array<string, mixed>  $hooks
     * @param  array<string, mixed>|list<string>  $critical
     */
    private function configure(array $hooks, array $critical = []): void
    {
        CrudConfig::updateOrCreate(
            ['model' => HookAbortStub::class, 'route' => ''],
            ['config' => [
                'crud' => HookAbortStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text', 'colsGravar' => true],
                ],
                'permissions' => [],
                'lifecycleHooks' => $hooks,
                'lifecycleHooksCritical' => $critical,
            ]]
        );
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => HookAbortStub::class]);
    }

    // ── The default, pinned ────────────────────────────────────────────────

    #[Test]
    public function a_failing_side_effect_hook_is_still_swallowed(): void
    {
        // The deliberate decision, and the reason the change is opt-in: an
        // `afterCreate` that notifies must not lose the record it notifies
        // about. Removing this behaviour would be the actual regression.
        $this->configure(['afterCreate' => '@AbortingHooks::afterCreateNotify']);

        $this->crud()
            ->set('formData.name', 'Widget')
            ->call('save')
            ->assertSet('formErrors._general', null);

        $this->assertDatabaseHas('items', ['name' => 'Widget']);
    }

    #[Test]
    public function the_swallowed_failure_is_still_logged(): void
    {
        // Silence in the UI, never in the log — that is what makes the default
        // defensible at all.
        $this->configure(['afterCreate' => '@AbortingHooks::afterCreateNotify']);

        $this->crud()->set('formData.name', 'Widget')->call('save');

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'afterCreate'))
            ->atLeast()->once();
    }

    // ── 1. The hook says stop ──────────────────────────────────────────────

    #[Test]
    public function a_hook_that_throws_the_abort_stops_the_create(): void
    {
        // The reporter's second case: `beforeCreate` generates a temporary
        // password and fails. Before this, the row was written with an empty
        // password and the user was told it worked.
        $this->configure(['beforeCreate' => '@AbortingHooks::beforeCreate']);

        $component = $this->crud()
            ->set('formData.name', 'Widget')
            ->call('save');

        $this->assertDatabaseMissing('items', ['name' => 'Widget']);

        $error = (string) $component->get('formErrors._general');

        $this->assertStringContainsString(
            'A senha temporária não pôde ser gerada.',
            $error,
            'A mensagem do hook tem de chegar ao usuário, e não só ao log.'
        );
    }

    #[Test]
    public function an_abort_before_the_write_does_not_say_the_record_was_saved(): void
    {
        $this->configure(['beforeCreate' => '@AbortingHooks::beforeCreate']);

        $error = (string) $this->crud()
            ->set('formData.name', 'Widget')
            ->call('save')
            ->get('formErrors._general');

        $this->assertStringContainsString(trans('ptah::ui.crud_hook_aborted', ['message' => '']), $error);
    }

    #[Test]
    public function a_hook_abort_on_update_leaves_the_row_untouched(): void
    {
        // The reporter's first case, and the one where a silent failure is
        // worst: the guard that stops a hash from being re-hashed.
        $row = HookAbortStub::create(['name' => 'Original', 'status' => 'ok']);

        $this->configure(['beforeUpdate' => '@AbortingHooks::beforeUpdateBoom']);

        $this->crud()
            ->call('openEdit', $row->id)
            ->set('formData.name', 'Changed')
            ->call('save');

        $this->assertDatabaseHas('items', ['id' => $row->id, 'name' => 'Original']);
    }

    // ── 2. Laravel's own rejection ─────────────────────────────────────────

    #[Test]
    public function a_validation_exception_from_a_hook_becomes_a_field_error(): void
    {
        // A hook that validates and is ignored lets the save proceed with the
        // data it refused. Propagated untouched so Livewire renders it where
        // the user is looking — on the field — instead of as a generic banner.
        $this->configure(['beforeCreate' => '@AbortingHooks::beforeCreateInvalid']);

        $this->crud()
            ->set('formData.name', 'Widget')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertDatabaseMissing('items', ['name' => 'Widget']);
    }

    // ── 3. Declared critical in the config ─────────────────────────────────

    /**
     * @return array<string, array{0: array<string, mixed>|list<string>}>
     */
    public static function criticalDeclarationProvider(): array
    {
        return [
            'map' => [['beforeCreate' => true]],
            'list' => [['beforeCreate']],
        ];
    }

    #[Test]
    #[DataProvider('criticalDeclarationProvider')]
    public function a_hook_declared_critical_aborts_on_any_failure(array $critical): void
    {
        // For a hook whose code you cannot change — a third-party class, or one
        // that throws a plain exception. Both shapes are accepted because both
        // are the obvious thing to write.
        $this->configure(['beforeCreate' => '@AbortingHooks::beforeCreateBoom'], $critical);

        $error = (string) $this->crud()
            ->set('formData.name', 'Widget')
            ->call('save')
            ->get('formErrors._general');

        $this->assertDatabaseMissing('items', ['name' => 'Widget']);
        $this->assertStringContainsString('cofre indisponível', $error);
    }

    #[Test]
    public function the_same_hook_without_the_declaration_still_saves(): void
    {
        // The counterpart that makes the test above mean something: it is the
        // DECLARATION doing the work, not the exception type.
        $this->configure(['beforeCreate' => '@AbortingHooks::beforeCreateBoom']);

        $this->crud()->set('formData.name', 'Widget')->call('save');

        $this->assertDatabaseHas('items', ['name' => 'Widget']);
    }

    #[Test]
    public function declaring_one_hook_critical_does_not_arm_the_others(): void
    {
        $this->configure(
            [
                'beforeCreate' => '@AbortingHooks::beforeCreateBoom',
                'afterCreate' => '@AbortingHooks::afterCreateNotify',
            ],
            ['afterCreate' => true],
        );

        // beforeCreate falha e é engolido (não declarado), o registro é gravado,
        // e só então o afterCreate crítico aborta — com o registro já lá.
        $error = (string) $this->crud()
            ->set('formData.name', 'Widget')
            ->call('save')
            ->get('formErrors._general');

        $this->assertDatabaseHas('items', ['name' => 'Widget']);
        $this->assertStringContainsString('serviço de notificação fora do ar', $error);
    }

    // ── after* aborts say the record survived ──────────────────────────────

    #[Test]
    public function an_after_hook_abort_reports_that_the_record_was_kept(): void
    {
        // Há registro gravado: não existe transação em volta do save, então
        // dizer "erro ao salvar" seria mentira sobre uma linha que existe.
        $this->configure(['afterCreate' => '@AbortingHooks::afterCreateBoom']);

        $error = (string) $this->crud()
            ->set('formData.name', 'Widget')
            ->call('save')
            ->get('formErrors._general');

        $this->assertDatabaseHas('items', ['name' => 'Widget']);
        $this->assertStringContainsString(trans('ptah::ui.crud_hook_after_failed', ['message' => '']), $error);
        $this->assertStringContainsString('o e-mail de boas-vindas falhou', $error);
    }

    // ── The object form of a declaration ───────────────────────────────────

    #[Test]
    public function the_inline_object_form_carries_both_handler_and_criticality(): void
    {
        $this->configure([
            'beforeCreate' => ['handler' => '@AbortingHooks::beforeCreateBoom', 'critical' => true],
        ]);

        $this->crud()->set('formData.name', 'Widget')->call('save');

        $this->assertDatabaseMissing('items', ['name' => 'Widget']);
    }

    #[Test]
    public function the_config_editor_survives_a_hook_written_as_an_object(): void
    {
        // Uma regressão que este trabalho poderia ter criado: um array chegando
        // numa propriedade `public string` é TypeError, e o modal de config
        // inteiro morre antes de renderizar.
        $this->configure([
            'beforeCreate' => ['handler' => '@AbortingHooks::beforeCreateBoom', 'critical' => true],
        ]);

        Livewire::test(CrudConfigEditor::class, ['model' => HookAbortStub::class])
            ->assertSet('hookBeforeCreate', '@AbortingHooks::beforeCreateBoom');
    }

    #[Test]
    public function a_hook_left_empty_is_not_a_hook(): void
    {
        // O editor grava `null` para um campo vazio, e os quatro estão sempre
        // presentes na chave.
        $this->configure(['beforeCreate' => null, 'afterCreate' => '', 'beforeUpdate' => [], 'afterUpdate' => null]);

        $this->crud()->set('formData.name', 'Widget')->call('save');

        $this->assertDatabaseHas('items', ['name' => 'Widget']);
    }
}
