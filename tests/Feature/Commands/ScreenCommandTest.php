<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Support\ScreenSummary;
use Ptah\Tests\TestCase;

/**
 * `ptah:screen` replaces reading a screen's JSON. It must keep every fact an
 * edit depends on — and cost a fraction of the JSON, or it has no reason to
 * exist.
 */
class ScreenCommandTest extends TestCase
{
    private function config(): array
    {
        $filler = ['colsAlign' => 'text-start', 'colsMinWidth' => '', 'colsCellClass' => '', 'colsCellStyle' => '', 'colsCellIcon' => '', 'colsHelper' => '', 'colsMaskTransform' => '', 'colsSDMode' => '', 'colsReverse' => false, 'colsRendererDecimals' => 2];

        return [
            'crud' => 'Catalog/Product',
            'displayName' => 'Produtos',
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true, 'colsIsFilterable' => true] + $filler,
                ['colsNomeFisico' => 'price', 'colsNomeLogico' => 'Preço', 'colsTipo' => 'number', 'colsGravar' => true, 'colsRenderer' => 'money', 'colsMask' => 'money_brl'] + $filler,
                ['colsNomeFisico' => 'category_id', 'colsNomeLogico' => 'Categoria', 'colsTipo' => 'searchdropdown', 'colsGravar' => true, 'colsSDModel' => 'App\\Models\\Catalog\\Category', 'colsSDLabel' => 'name'] + $filler,
                ['colsNomeFisico' => 'cost', 'colsNomeLogico' => 'Custo', 'colsTipo' => 'number', 'colsVisibleList' => false, 'colsPermission' => 'product.cost'] + $filler,
                ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'Abrir', 'colsTipo' => 'action', 'actionType' => 'link', 'actionValue' => '/products/%id%'],
            ],
            'customFilters' => [['field' => 'status', 'colsFilterType' => 'select', 'label' => 'Status', 'defaultOperator' => '=']],
            'contitionStyles' => [['field' => 'stock', 'condition' => '<', 'value' => 5, 'style' => 'color:red']],
            'permissions' => ['permissionIdentifier' => 'pageProduct', 'delete' => 'product.delete', 'showTrashButton' => false],
            'lifecycleHooks' => ['beforeCreate' => 'App\\Hooks\\ProductHooks@beforeCreate', 'afterCreate' => null],
            'uiPreferences' => ['perPage' => 50, 'compactMode' => false, 'stickyHeader' => true],
            'exportConfig' => ['enabled' => true, 'maxRows' => 10000, 'orientation' => 'landscape', 'formats' => ['excel', 'pdf'], 'chunkSize' => 500],
            'cacheStrategy' => ['enabled' => true, 'ttl' => 300, 'tags' => []],
        ];
    }

    #[Test]
    public function the_summary_keeps_what_an_edit_depends_on(): void
    {
        $text = ScreenSummary::toText(ScreenSummary::build('Catalog/Product', '', $this->config()));

        foreach ([
            'Catalog/Product  [global]  "Produtos"',
            'permission: pageProduct  gates: delete=product.delete  off: showTrashButton',
            'name  text  "Nome"  [form,required,filter]',
            'price  number  "Preço"  [form]  renderer=money mask=money_brl',
            'category_id  searchdropdown  "Categoria"  [form]  sd=Category.name',
            'cost  number  "Custo"  [hidden]  perm=product.cost',
            '"Abrir" link /products/%id%',
            'status select "Status"',
            'stock < 5',
            'hooks: beforeCreate=App\\Hooks\\ProductHooks@beforeCreate',
            'settings: perPage=50  export=on',
        ] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }

        $this->assertStringNotContainsString('afterCreate', $text, 'Hook vazio nao e informacao.');
    }

    #[Test]
    public function the_summary_costs_a_fraction_of_the_json(): void
    {
        $config = $this->config();
        $text = ScreenSummary::toText(ScreenSummary::build('Catalog/Product', '', $config));

        $this->assertLessThan(strlen((string) json_encode($config)) / 2, strlen($text));
    }

    #[Test]
    public function it_finds_the_screen_by_key_fqcn_or_short_name(): void
    {
        CrudConfig::create(['model' => 'Catalog/Product', 'route' => '', 'config' => $this->config()]);
        CrudConfig::create(['model' => 'Catalog/Product', 'route' => 'admin/products', 'config' => ['cols' => []]]);

        foreach (['Catalog/Product', 'App\\Models\\Catalog\\Product', 'Product', 'product'] as $input) {
            $this->artisan('ptah:screen', ['model' => $input])
                ->expectsOutputToContain('Catalog/Product  [global]')
                ->assertExitCode(0);
        }

        $this->artisan('ptah:screen', ['model' => 'Product', '--route' => 'admin/products'])
            ->expectsOutputToContain('[route admin/products]')
            ->doesntExpectOutputToContain('[global]')
            ->assertExitCode(0);
    }

    #[Test]
    public function an_ambiguous_short_name_is_not_guessed(): void
    {
        CrudConfig::create(['model' => 'Catalog/Product', 'route' => '', 'config' => ['cols' => []]]);
        CrudConfig::create(['model' => 'Legacy/Product', 'route' => '', 'config' => ['cols' => []]]);

        $this->artisan('ptah:screen', ['model' => 'Product'])
            ->expectsOutputToContain('No screen configured')
            ->assertExitCode(1);
    }

    #[Test]
    public function json_output(): void
    {
        CrudConfig::create(['model' => 'Catalog/Product', 'route' => '', 'config' => $this->config()]);

        $this->artisan('ptah:screen', ['model' => 'Catalog/Product', '--json' => true])
            ->expectsOutputToContain('"permission":"pageProduct"')
            ->assertExitCode(0);
    }
}
