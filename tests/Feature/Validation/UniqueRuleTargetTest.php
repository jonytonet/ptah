<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Validation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Services\Crud\FormValidatorService;
use Ptah\Tests\TestCase;

class UniqueRuleItem extends Model
{
    protected $table = 'items';

    protected $guarded = [];
}

/**
 * 1.50.0: the docs showed `unique:products,sku` (a TABLE) and the validator
 * only took a model class — every such rule was skipped in silence.
 */
class UniqueRuleTargetTest extends TestCase
{
    private function errors(string $rule, array $data = []): array
    {
        $cols = [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsValidations' => [$rule]]];

        return (new FormValidatorService)->validate(['name' => 'Alfa'] + $data, $cols);
    }

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('items')->insert(['name' => 'Alfa', 'created_at' => now(), 'updated_at' => now()]);
    }

    #[Test]
    public function a_table_name_is_enforced(): void
    {
        $this->assertArrayHasKey('name', $this->errors('unique:items,name'));
    }

    #[Test]
    public function a_model_class_is_still_enforced(): void
    {
        $this->assertArrayHasKey('name', $this->errors('unique:'.UniqueRuleItem::class.',name'));
    }

    #[Test]
    public function the_record_under_edit_is_ignored(): void
    {
        $id = DB::table('items')->where('name', 'Alfa')->value('id');
        $this->assertSame([], $this->errors('unique:items,name', ['id' => $id]));
    }

    #[Test]
    public function an_unresolvable_target_is_logged_not_silent(): void
    {
        Log::spy();
        $this->assertSame([], $this->errors('unique:nope_table,name'));
        Log::shouldHaveReceived('warning')->once();
    }
}
