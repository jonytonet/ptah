<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Contracts\SearchDropdownLabelSource;
use Ptah\Contracts\SearchDropdownSource;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class SdBranchSearchOnly implements SearchDropdownSource
{
    /** @return list<array{value: string, label: string}> */
    public function searchDropdown(string $term, string $key): array
    {
        return array_values(array_filter(
            [['value' => '7', 'label' => 'Filial Centro'], ['value' => '77', 'label' => 'Filial Norte']],
            fn ($b) => $term === '' || str_contains($b['value'], $term) || str_contains($b['label'], $term)
        ));
    }
}

class SdBranchWithLabels extends SdBranchSearchOnly implements SearchDropdownLabelSource
{
    public function searchDropdownLabel(mixed $value): ?string
    {
        return $value === '7' || $value === 7 ? 'Centro (via interface)' : null;
    }
}

class SdNotice extends Model
{
    protected $table = 'sd_notices';

    protected $guarded = [];
}

/**
 * 1.48.0 (Engepecas): a service-mode searchdropdown (an ERP branch, no model
 * here) opened EMPTY when editing, though the value was saved.
 */
class CrudSdServiceLabelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sd_notices', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('branch_code')->nullable();
            $t->timestamps();
        });
    }

    private function configure(string $service): void
    {
        CrudConfig::create(['model' => SdNotice::class, 'route' => '', 'config' => [
            'crud' => SdNotice::class,
            'cols' => [
                ['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Titulo', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'branch_code', 'colsNomeLogico' => 'Filial', 'colsTipo' => 'searchdropdown', 'colsGravar' => true,
                    'colsSDTipo' => 'service', 'colsSDModel' => $service.'\search'],
            ],
            'permissions' => [],
        ]]);
    }

    private function notice(): int
    {
        return (int) DB::table('sd_notices')->insertGetId(['title' => 'Aviso', 'branch_code' => '7', 'created_at' => now(), 'updated_at' => now()]);
    }

    #[Test]
    public function the_search_method_resolves_the_label_by_exact_value(): void
    {
        $this->configure(SdBranchSearchOnly::class);

        Livewire::test(BaseCrud::class, ['model' => SdNotice::class])
            ->call('openEdit', $this->notice())
            ->assertSet('sdLabels.branch_code', 'Filial Centro'); // nao "Filial Norte" (77 tambem contem 7)
    }

    #[Test]
    public function a_label_source_answers_directly(): void
    {
        $this->configure(SdBranchWithLabels::class);

        Livewire::test(BaseCrud::class, ['model' => SdNotice::class])
            ->call('openEdit', $this->notice())
            ->assertSet('sdLabels.branch_code', 'Centro (via interface)');
    }
}
