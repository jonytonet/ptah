<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Tests\TestCase;

/**
 * Achado #8 do PetPlace: sem displayName o modal dizia "Novo Clients/Pet" —
 * o caminho do model, que o usuario final nao deveria ver.
 */
class CrudModalTitleTest extends TestCase
{
    private function title(array $config): string
    {
        CrudConfig::updateOrCreate(['model' => 'Clients/PetOwner', 'route' => ''], ['config' => ['crud' => 'Clients/PetOwner', 'cols' => []] + $config]);

        return Livewire::test(BaseCrud::class, ['model' => 'Clients/PetOwner'])->instance()->humanTitle(singular: true);
    }

    #[Test]
    public function without_a_display_name_it_is_the_short_name_in_words_never_the_path(): void
    {
        $this->assertSame('Pet Owner', $this->title([]));
        $this->assertSame('Pet Owner', $this->title(['displayName' => '']), 'displayName vazio (o que o editor grava) nao pode virar titulo vazio.');
    }

    #[Test]
    public function the_display_names_win_singular_first_for_the_modal(): void
    {
        $this->assertSame('Tutores', $this->title(['displayName' => 'Tutores']));
        $this->assertSame('Tutor', $this->title(['displayName' => 'Tutores', 'displayNameSingular' => 'Tutor']));
    }

    #[Test]
    public function the_page_object_label_comes_before_the_model_name(): void
    {
        config(['ptah.modules.permissions' => true]);
        $page = PtahPage::create(['slug' => 'clients', 'name' => 'Clients', 'is_active' => true]);
        PageObject::create(['page_id' => $page->id, 'section' => 'main', 'obj_key' => 'clients.owners', 'obj_label' => 'Tutores de pets', 'obj_type' => 'page', 'obj_order' => 1, 'is_active' => true]);

        $this->assertSame('Tutores de pets', $this->title(['permissions' => ['permissionIdentifier' => 'clients.owners']]));
    }

    #[Test]
    public function the_modal_renders_the_human_title(): void
    {
        CrudConfig::updateOrCreate(['model' => 'Clients/PetOwner', 'route' => ''], ['config' => ['crud' => 'Clients/PetOwner', 'cols' => []]]);

        Livewire::test(BaseCrud::class, ['model' => 'Clients/PetOwner'])
            ->assertSee(__('ptah::ui.modal_new_prefix').' Pet Owner')
            ->assertDontSee(__('ptah::ui.modal_new_prefix').' Clients/PetOwner');
    }
}
