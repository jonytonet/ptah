<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Crud\CrudScreen;
use Ptah\Models\CrudConfig;
use Ptah\Support\PtahMask;
use Ptah\Tests\TestCase;

class FormItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/** A class-based lifecycle hook that sends the user somewhere after saving. */
class FormRedirectHook
{
    public static string $target = '/items/:id';

    public function afterCreate(array $data, Model $record, object $component): void
    {
        $component->redirect(str_replace(':id', (string) $record->getKey(), self::$target));
    }
}

/**
 * Form parity of the headless API with the Livewire modal (1.59.0): what the
 * form needs beyond label and type (sections, image accept, the dropdown's
 * placeholder/direction/parent), the modal title, image previews, the
 * whitelisted lookup options and the redirect a hook asks for after saving.
 */
class FormParityApiTest extends TestCase
{
    private const BASE = '/ptah/api/crud';

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web']);
        $app['config']->set('ptah.crud.hook_namespaces', ['Ptah\\Tests\\Feature\\Api\\Crud']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(new GenericUser(['id' => 9]));
        FormRedirectHook::$target = '/items/:id';

        CrudScreen::register('items', FormItem::class)->configRoute('');
        $this->configure();

        FormItem::create(['name' => 'Alfa', 'status' => 'fotos/alfa.png', 'amount' => 1]);
        FormItem::create(['name' => 'Beta', 'status' => '', 'amount' => 2]);
    }

    /** @param  array<string, mixed>  $extra */
    private function configure(array $extra = []): void
    {
        CrudConfig::updateOrCreate(['model' => FormItem::class, 'route' => ''], ['config' => $extra + [
            'crud' => FormItem::class,
            'displayName' => 'Conta',
            'displayNameGender' => 'f',
            'cols' => [
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number'],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true,
                    'colsFormBlock' => '  Dados gerais  '],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Foto', 'colsTipo' => 'image', 'colsGravar' => true,
                    'colsFormBlock' => 'Mídia', 'colsUploadAllowedTypes' => 'png, jpg'],
                ['colsNomeFisico' => 'amount', 'colsNomeLogico' => 'Parecido com', 'colsTipo' => 'searchdropdown', 'colsGravar' => true,
                    'colsFormBlock' => 'Mídia',
                    'colsSDModel' => FormItem::class, 'colsSDLabel' => 'name', 'colsSDValor' => 'id', 'colsSDTipo' => 'model',
                    'colsSDLabelTwo' => 'status', 'colsSDPlaceholder' => 'Escolha um item', 'colsSDStartList' => 'top',
                    'colsSDDependsOn' => 'name', 'colsSDFilterColumn' => 'name'],
            ],
            'permissions' => [],
        ]]);
    }

    /** @return array<string, array<string, mixed>> */
    private function fields(): array
    {
        $form = $this->getJson(self::BASE.'/items/schema')->assertOk()->json('form');

        return array_column($form, null, 'field');
    }

    #[Test]
    public function the_form_fields_carry_sections_accept_and_the_dropdown_settings(): void
    {
        $fields = $this->fields();

        $this->assertSame('Dados gerais', $fields['name']['block']);
        $this->assertNull($fields['name']['accept']);
        $this->assertNull($fields['name']['lookup']);

        $this->assertSame('Mídia', $fields['status']['block']);
        $this->assertSame('.png,.jpg', $fields['status']['accept']);

        $this->assertSame([
            'placeholder' => 'Escolha um item',
            'startList' => 'top',
            'dependsOn' => 'name',
            'dependsOnLabel' => 'Nome',
        ], $fields['amount']['lookup']);

        // Only named keys: not the dropdown's model nor its filters.
        $raw = $this->getJson(self::BASE.'/items/schema')->getContent();
        $this->assertStringNotContainsString('colsSD', $raw);
        $this->assertStringNotContainsString(addslashes(FormItem::class), $raw);
    }

    #[Test]
    public function an_image_without_allowed_types_accepts_any_image_and_a_field_without_block_has_none(): void
    {
        $this->configure(['cols' => [
            ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
            ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Foto', 'colsTipo' => 'image', 'colsGravar' => true],
        ]]);

        $fields = $this->fields();
        $this->assertNull($fields['name']['block']);
        $this->assertSame('image/*', $fields['status']['accept']);
    }

    #[Test]
    public function a_registered_mask_tells_its_placeholder_input_mode_and_length(): void
    {
        PtahMask::define('cpf_teste', ['pattern' => '000.000.000-00']);

        try {
            $this->configure(['cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'CPF', 'colsTipo' => 'text', 'colsGravar' => true, 'colsMask' => 'cpf_teste'],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'text', 'colsGravar' => true],
            ]]);

            $fields = $this->fields();
            $this->assertSame('000.000.000-00', $fields['name']['maskPlaceholder']);
            $this->assertSame('numeric', $fields['name']['inputMode']);
            $this->assertSame(14, $fields['name']['maxLength']);
            $this->assertNull($fields['status']['maskPlaceholder']);
            $this->assertNull($fields['status']['maxLength']);
        } finally {
            PtahMask::flush();
        }
    }

    #[Test]
    public function the_form_opens_with_the_modal_title_by_gender_and_the_image_previews(): void
    {
        app()->setLocale('pt_BR');
        $blank = $this->getJson(self::BASE.'/items/create')->assertOk()->json();
        $this->assertSame('Nova Conta', $blank['title']);
        // A form with a required field says what the * means (1.61.0).
        $this->assertSame(__('ptah::ui.form_required_hint'), $blank['subtitle']);
        $this->assertSame([], $blank['previews']);

        $id = FormItem::where('name', 'Alfa')->value('id');
        $record = $this->getJson(self::BASE."/items/{$id}")->assertOk()->json();
        $this->assertSame(__('ptah::ui.modal_edit_prefix').' Conta', $record['title']);
        $this->assertSame(__('ptah::ui.form_required_hint'), $record['subtitle']);
        $this->assertSame(asset('storage/fotos/alfa.png'), $record['previews']['status']);

        // A copy is a new record: "Nova", with the copied image's preview.
        $copy = $this->postJson(self::BASE."/items/{$id}/duplicate")->assertOk()->json();
        $this->assertSame('Nova Conta', $copy['title']);

        // An empty image has no preview.
        $empty = FormItem::where('name', 'Beta')->value('id');
        $this->assertSame([], $this->getJson(self::BASE."/items/{$empty}")->json('previews'));
    }

    #[Test]
    public function a_masculine_screen_opens_as_novo(): void
    {
        $this->configure(['displayNameGender' => 'm']);
        app()->setLocale('pt_BR');

        $this->assertSame('Novo Conta', $this->getJson(self::BASE.'/items/create')->json('title'));
    }

    #[Test]
    public function the_lookup_answers_the_extra_lines_and_waits_for_the_parent(): void
    {
        // The parent (name) is empty: the dependent dropdown is locked, as in the modal.
        $this->assertSame([], $this->postJson(self::BASE.'/items/lookup/amount', ['q' => '', 'context' => []])->assertOk()->json('data'));

        $found = $this->postJson(self::BASE.'/items/lookup/amount', ['q' => '', 'context' => ['name' => 'Alfa']])->assertOk()->json('data');
        $this->assertCount(1, $found);
        $this->assertSame(['value', 'label', 'labelTwo'], array_keys($found[0]));
        $this->assertSame('Alfa', $found[0]['label']);
        $this->assertSame('fotos/alfa.png', $found[0]['labelTwo']);
    }

    #[Test]
    public function a_save_answers_the_redirect_a_hook_asked_for(): void
    {
        $this->configure(['lifecycleHooks' => ['afterCreate' => '@'.FormRedirectHook::class.'::afterCreate']]);

        $json = $this->postJson(self::BASE.'/items', ['data' => ['name' => 'Delta']])->assertCreated()->json();
        $this->assertTrue($json['ok']);
        $this->assertSame('/items/'.$json['id'], $json['redirect']);
    }

    #[Test]
    public function a_save_without_redirect_answers_null_and_a_script_url_never_leaves(): void
    {
        $this->assertNull($this->postJson(self::BASE.'/items', ['data' => ['name' => 'Delta']])->assertCreated()->json('redirect'));

        $this->configure(['lifecycleHooks' => ['afterCreate' => '@'.FormRedirectHook::class.'::afterCreate']]);
        FormRedirectHook::$target = 'javascript:alert(:id)';

        $json = $this->postJson(self::BASE.'/items', ['data' => ['name' => 'Epsilon']])->assertCreated()->json();
        $this->assertTrue($json['ok']);
        $this->assertNull($json['redirect']);
    }

    #[Test]
    public function a_failed_save_answers_no_redirect(): void
    {
        $this->configure(['lifecycleHooks' => ['afterCreate' => '@'.FormRedirectHook::class.'::afterCreate']]);

        $json = $this->postJson(self::BASE.'/items', ['data' => ['name' => '']])->assertStatus(422)->json();
        $this->assertFalse($json['ok']);
        $this->assertNull($json['redirect']);
        $this->assertArrayHasKey('name', $json['errors']);
    }
}
