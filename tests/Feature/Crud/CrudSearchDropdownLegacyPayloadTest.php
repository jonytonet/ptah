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

class SdLegacyPayloadStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * Pins the exact result-item shape for a v1.20-style config — one that
 * predates labelTwo/labelThree/masks entirely (only colsSDModel/colsSDLabel/
 * colsSDValor). Every result item must remain EXACTLY {value, label}, with no
 * extra keys and no coercion of the label's type/null-ness — a real,
 * previously-saved config must render byte-identically after this feature.
 */
class CrudSearchDropdownLegacyPayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CrudConfig::create([
            'model' => SdLegacyPayloadStub::class,
            'route' => '',
            'config' => [
                'crud' => SdLegacyPayloadStub::class,
                'cols' => [
                    ['colsNomeFisico' => 'id', 'colsNomeLogico' => 'ID', 'colsTipo' => 'number', 'colsGravar' => false],
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text', 'colsGravar' => true],
                    [
                        // A v1.20 config: only the pre-existing keys, nothing new.
                        'colsNomeFisico' => 'category_id',
                        'colsNomeLogico' => 'Target',
                        'colsTipo' => 'searchdropdown',
                        'colsGravar' => true,
                        'colsSDModel' => SdLegacyPayloadStub::class,
                        'colsSDLabel' => 'name',
                        'colsSDValor' => 'id',
                    ],
                ],
                'permissions' => [],
            ],
        ]);

        SdLegacyPayloadStub::create(['name' => 'Alpha']);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => SdLegacyPayloadStub::class]);
    }

    #[Test]
    public function each_result_item_has_exactly_value_and_label(): void
    {
        $results = $this->crud()->call('searchDropdown', 'category_id', 'alpha')->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
        $this->assertSame(['value', 'label'], array_keys($results[0]));
    }

    #[Test]
    public function the_label_value_is_unchanged_by_the_new_masking_layer(): void
    {
        $results = $this->crud()->call('searchDropdown', 'category_id', 'alpha')->get('sdResults')['category_id'];

        $this->assertSame('Alpha', $results[0]['label']);
    }

    #[Test]
    public function open_dropdown_also_returns_the_legacy_shape(): void
    {
        $results = $this->crud()->call('openDropdown', 'category_id')->get('sdResults')['category_id'];

        $this->assertCount(1, $results);
        $this->assertSame(['value', 'label'], array_keys($results[0]));
    }
}
