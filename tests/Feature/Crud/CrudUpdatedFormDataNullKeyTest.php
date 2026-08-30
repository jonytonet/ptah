<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;
use ReflectionMethod;

class NullKeyStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * Every plain `<x-forge-select>` in every BaseCrud form threw HTTP 419 in
 * production, and only in production.
 *
 * `updatedFormData()` declared `string $key`. Livewire passes the changed
 * sub-key for `formData.status`, but passes NULL when the whole `formData`
 * array is replaced at once — which a plain select's `wire:model` does. The
 * non-nullable signature turned that into a TypeError, and Livewire converts an
 * unhandled TypeError during an update into a bare `419 This page has expired`
 * once `APP_DEBUG` is off. With debug on, the same code raised a TypeError that
 * looked unrelated, which is why it survived to a production host.
 *
 * Searchdropdowns were never affected: they write through
 * `selectDropdownOption()`, which always passes an explicit string field. That
 * asymmetry is what made the report confusing, so it is pinned here too.
 */
class CrudUpdatedFormDataNullKeyTest extends TestCase
{
    private function makeConfig(): void
    {
        CrudConfig::updateOrCreate(
            ['model' => NullKeyStub::class, 'route' => ''],
            ['config' => [
                'crud' => NullKeyStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'select', 'colsGravar' => true],
                ],
                'permissions' => [],
            ]]
        );
    }

    #[Test]
    public function the_hook_accepts_a_null_key(): void
    {
        // The signature IS the bug. Asserting it directly means this test fails
        // for the real reason even if a future refactor changes what the body
        // does with the key.
        $method = new ReflectionMethod(BaseCrud::class, 'updatedFormData');
        $key = $method->getParameters()[1];

        $this->assertTrue(
            $key->allowsNull(),
            'updatedFormData($value, $key): $key precisa aceitar null. '.
            'O Livewire passa null quando o array formData inteiro e substituido '.
            '(o que um <select> comum faz), e uma assinatura nao-nulavel vira '.
            'TypeError -> HTTP 419 com APP_DEBUG=false.'
        );
    }

    #[Test]
    public function replacing_the_whole_form_data_array_does_not_blow_up(): void
    {
        $this->makeConfig();

        // Reproduces what a plain select's wire:model does: the component ends
        // up with the whole array replaced, and the hook fires with no sub-key.
        $component = Livewire::test(BaseCrud::class, ['model' => NullKeyStub::class]);

        $component->instance()->updatedFormData(['status' => 'active'], null);

        // Reaching this line at all is the assertion: before the fix the call
        // above raised a TypeError, which Livewire turns into a 419.
        $this->assertTrue(true);
    }

    #[Test]
    public function a_named_key_still_propagates_to_dependents_and_formulas(): void
    {
        $this->makeConfig();

        // The early return must not swallow the normal path — the whole reason
        // the hook exists is to reset cascading dropdowns and run onChange
        // formulas when a real field changes.
        $component = Livewire::test(BaseCrud::class, ['model' => NullKeyStub::class])
            ->set('formData.status', 'active');

        $component->assertSet('formData.status', 'active')
            ->assertHasNoErrors();
    }

    #[Test]
    public function the_searchdropdown_path_still_passes_an_explicit_field(): void
    {
        // Pins the asymmetry that made the bug report hard to read: this path
        // never saw a null key, which is why searchdropdowns kept working while
        // every plain select broke.
        $method = new ReflectionMethod(BaseCrud::class, 'selectDropdownOption');
        $field = $method->getParameters()[0];

        $this->assertFalse(
            $field->allowsNull(),
            'selectDropdownOption() sempre recebe um campo explicito — e por isso '.
            'o searchdropdown nunca reproduziu o 419.'
        );
    }
}
