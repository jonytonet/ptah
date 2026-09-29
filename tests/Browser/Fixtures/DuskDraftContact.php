<?php

declare(strict_types=1);

namespace Ptah\Tests\Browser\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Ptah\Models\CrudConfig;

/**
 * Screen with `formDraft` on, for FormDraftBrowserTest. The Dusk server
 * rebuilds the app (and its in-memory database) on every request, so the two
 * records are seeded every time and always get ids 1 and 2.
 */
class DuskDraftContact extends Model
{
    protected $table = 'dusk_draft_contacts';

    protected $fillable = ['name', 'phone'];

    public static function seedFixtures(): void
    {
        if (! Schema::hasTable('dusk_draft_contacts')) {
            Schema::create('dusk_draft_contacts', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('phone')->nullable();
                $t->timestamps();
            });
        }

        if (self::query()->count() === 0) {
            self::create(['name' => 'Ana', 'phone' => '111']);
            self::create(['name' => 'Bia', 'phone' => '222']);
        }

        CrudConfig::firstOrCreate(['model' => self::class], ['route' => '', 'config' => [
            'crud' => self::class,
            'cols' => [
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsVisibleList' => true],
                ['colsNomeFisico' => 'phone', 'colsNomeLogico' => 'Telefone', 'colsTipo' => 'text', 'colsGravar' => true, 'colsVisibleList' => true],
            ],
            'permissions' => [],
            'formDraft' => ['enabled' => true],
        ]]);
    }
}
