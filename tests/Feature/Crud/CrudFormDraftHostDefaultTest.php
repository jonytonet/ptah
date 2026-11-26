<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Livewire\BaseCrud\CrudConfig as CrudConfigEditor;
use Ptah\Models\CrudConfig;
use Ptah\PtahServiceProvider;
use Ptah\Tests\TestCase;

class HostDraftItem extends Model
{
    protected $table = 'host_draft_items';

    protected $guarded = [];
}

/**
 * The host's default for the form draft (1.60.3, yelll).
 *
 * `formDraft` was opt-in per screen only, so a host that wanted it everywhere
 * had to write it into every screen — 174 of them in yelll — and the ones it
 * missed asked "discard changes?" instead of keeping what was typed.
 * `ptah.crud.form_draft` is the default for a screen without the key; a screen
 * that says always wins; and opening and saving the editor must not freeze
 * today's default into the screen.
 */
class CrudFormDraftHostDefaultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ptah.modules.permissions' => false, 'ptah.crud.config_editor' => true]);
        $this->actingAs(new GenericUser(['id' => 5]));

        Schema::create('host_draft_items', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });
    }

    private function screen(?array $draft): void
    {
        CrudConfig::updateOrCreate(['model' => HostDraftItem::class, 'route' => ''], ['config' => array_filter([
            'crud' => HostDraftItem::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true],
            ],
            'permissions' => [],
            'formDraft' => $draft,
        ], fn ($v) => $v !== null)]);
    }

    #[Test]
    public function without_the_key_the_screen_follows_the_host_default(): void
    {
        $this->screen(null);

        config(['ptah.crud.form_draft' => false]);
        Livewire::test(BaseCrud::class, ['model' => HostDraftItem::class])->call('openCreate')->assertNotDispatched('ptah:form-draft');

        config(['ptah.crud.form_draft' => true]);
        Livewire::test(BaseCrud::class, ['model' => HostDraftItem::class])->call('openCreate')->assertDispatched('ptah:form-draft');
    }

    #[Test]
    public function a_screen_that_says_wins_over_the_host_default(): void
    {
        config(['ptah.crud.form_draft' => true]);
        $this->screen(['enabled' => false]);
        Livewire::test(BaseCrud::class, ['model' => HostDraftItem::class])->call('openCreate')->assertNotDispatched('ptah:form-draft');

        config(['ptah.crud.form_draft' => false]);
        $this->screen(['enabled' => true]);
        Livewire::test(BaseCrud::class, ['model' => HostDraftItem::class])->call('openCreate')->assertDispatched('ptah:form-draft');
    }

    #[Test]
    public function saving_in_the_editor_does_not_freeze_the_host_default_into_the_screen(): void
    {
        config(['ptah.crud.form_draft' => true]);
        $this->screen(null);

        Livewire::test(CrudConfigEditor::class, ['model' => HostDraftItem::class, 'configRoute' => ''])
            ->call('openModal')
            ->assertSet('featureFormDraft', true)
            ->call('save');

        $saved = CrudConfig::where('model', HostDraftItem::class)->where('route', '')->sole()->config;
        $this->assertArrayNotHasKey('enabled', (array) ($saved['formDraft'] ?? []), 'The editor wrote the default into the screen.');

        // Unticking it is a decision of the screen, and is kept.
        Livewire::test(CrudConfigEditor::class, ['model' => HostDraftItem::class, 'configRoute' => ''])
            ->call('openModal')
            ->set('featureFormDraft', false)
            ->call('save');

        $saved = CrudConfig::where('model', HostDraftItem::class)->where('route', '')->sole()->config;
        $this->assertFalse($saved['formDraft']['enabled']);
    }

    #[Test]
    public function the_env_switch_reaches_a_config_published_before_the_key_existed(): void
    {
        // Found in yelll (1.60.3): its config/ptah.php has a `crud` block from an
        // older release, the shallow merge never brought `form_draft` in, and
        // PTAH_CRUD_FORM_DRAFT=true did nothing.
        config(['ptah.crud' => ['responsive_cards' => false]]);
        putenv('PTAH_CRUD_FORM_DRAFT=true');
        $_ENV['PTAH_CRUD_FORM_DRAFT'] = $_SERVER['PTAH_CRUD_FORM_DRAFT'] = 'true';

        try {
            $provider = new PtahServiceProvider($this->app);
            (fn () => $this->fillMissingNestedConfig('crud', ['form_draft']))->call($provider);
        } finally {
            putenv('PTAH_CRUD_FORM_DRAFT');
            unset($_ENV['PTAH_CRUD_FORM_DRAFT'], $_SERVER['PTAH_CRUD_FORM_DRAFT']);
        }

        $this->assertTrue((bool) config('ptah.crud.form_draft'));
        // The host's own keys are untouched.
        $this->assertFalse(config('ptah.crud.responsive_cards'));

        $source = (string) file_get_contents(dirname(__DIR__, 3).'/src/PtahServiceProvider.php');
        $this->assertStringContainsString("fillMissingNestedConfig('crud', ['form_draft', 'filter_panel'])", $source);
    }
}
