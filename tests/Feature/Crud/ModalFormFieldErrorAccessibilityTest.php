<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

// ── Stub model on the `items` table ──────────────────────────────────────────

class ModalFormFieldErrorAccessibilityStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * Guards the accessibility wiring of the BaseCrud's inline "select" field
 * error state in _modal-form.blade.php — see FIX 2 of the Onda 3
 * accessibility audit. The plain <x-forge-input> path is covered separately
 * by ForgeInputAriaInvalidTest; this covers the OTHER field renderer that
 * partial owns directly (the inline select div), which had none of the
 * aria-invalid/aria-describedby wiring and painted its error message with
 * the failing `text-red-500` utility (3.76:1 against white).
 */
class ModalFormFieldErrorAccessibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CrudConfig::create([
            'model' => ModalFormFieldErrorAccessibilityStub::class,
            'route' => '',
            'config' => [
                'crud' => ModalFormFieldErrorAccessibilityStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    [
                        'colsNomeFisico' => 'name',
                        'colsNomeLogico' => 'Name',
                        'colsTipo' => 'text',
                        'colsGravar' => true,
                        'colsRequired' => true,
                    ],
                    [
                        'colsNomeFisico' => 'status',
                        'colsNomeLogico' => 'Status',
                        'colsTipo' => 'select',
                        'colsGravar' => true,
                        'colsRequired' => true,
                        'colsSelect' => ['Ativo' => 'active', 'Inativo' => 'inactive'],
                    ],
                ],
                'permissions' => [],
            ],
        ]);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => ModalFormFieldErrorAccessibilityStub::class]);
    }

    #[Test]
    public function required_select_left_empty_gets_aria_invalid_and_a_matching_message_id(): void
    {
        $component = $this->crud()
            ->call('prepareCreate')
            ->set('formData.name', 'Something')
            ->call('save');

        $this->assertArrayHasKey('status', $component->get('formErrors'), 'expected the required select to fail validation');

        $html = $component->html();

        $this->assertStringContainsString('aria-invalid="true" aria-describedby="ptah-form-err-status"', $html);
        $this->assertStringContainsString('id="ptah-form-err-status"', $html);
        $this->assertStringContainsString('ptah-c-field_err', $html);
    }

    #[Test]
    public function the_error_message_no_longer_uses_the_failing_text_red_500_utility(): void
    {
        $component = $this->crud()
            ->call('prepareCreate')
            ->set('formData.name', 'Something')
            ->call('save');

        $html = $component->html();

        $this->assertStringContainsString('ptah-form-err-status', $html, 'the field must still be in an error state for this assertion to mean anything');
        $this->assertDoesNotMatchRegularExpression('/text-xs\s+text-red-500/', $html);
    }

    #[Test]
    public function no_error_emits_no_aria_invalid_for_the_select_field(): void
    {
        $html = $this->crud()->call('prepareCreate')->html();

        $this->assertStringNotContainsString('aria-invalid', $html);
        $this->assertStringNotContainsString('ptah-form-err-status', $html);
    }
}
