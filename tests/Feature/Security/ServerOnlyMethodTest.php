<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Security;

use Illuminate\Database\Eloquent\Model;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Livewire\SearchDropdown\SearchDropdown;
use Ptah\Models\CrudConfig;
use Ptah\Support\ServerOnly;
use Ptah\Tests\TestCase;
use ReflectionClass;
use ReflectionMethod;

class ServerOnlyItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status'];
}

/**
 * CRITICAL, found by the 28/09/2026 surface audit and fixed in 1.41.6.
 *
 * Livewire lets the browser call every public method a component declares,
 * with arguments it picks, and returns the result:
 *  - SearchDropdown::formatValue($v, 'Illuminate\Filesystem\Filesystem@get')
 *    returned any file (`../.env`), and `DatabaseManager@unprepared` ran SQL;
 *  - BaseCrud::formatCell(['colsMetodoCustom' => '…Service\delete(5)'], …)
 *    called any method of any App\Services class.
 * Both stay public for views and hosts, and are refused to the client.
 */
class ServerOnlyMethodTest extends TestCase
{
    /**
     * Public methods that return something and ARE meant for the browser.
     * Everything else that returns a value must be #[ServerOnly].
     */
    private const CLIENT_RETURNING = [
        'getListeners', 'render', 'queryStringHandlesPagination',          // framework
        'getPage', 'previousPage', 'nextPage', 'gotoPage', 'resetPage', 'setPage', // WithPagination
        'downloadAttachment',                                             // a download response
        'search',                                                         // SearchDropdown: its job, scoped by its own config
    ];

    #[Test]
    public function the_browser_cannot_call_format_cell(): void
    {
        CrudConfig::updateOrCreate(['model' => ServerOnlyItem::class, 'route' => ''], ['config' => [
            'crud' => ServerOnlyItem::class,
            'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text']],
            'permissions' => [],
        ]]);

        $this->expectException(MethodNotFoundException::class);

        Livewire::test(BaseCrud::class, ['model' => ServerOnlyItem::class])
            ->call('formatCell', ['colsMetodoCustom' => 'Anything\\Service\\delete(1)'], []);
    }

    #[Test]
    public function the_browser_cannot_call_the_search_dropdown_formatter(): void
    {
        $this->expectException(MethodNotFoundException::class);

        Livewire::test(SearchDropdown::class)
            ->call('formatValue', '../.env', 'Illuminate\\Filesystem\\Filesystem@get');
    }

    #[Test]
    public function php_still_calls_them(): void
    {
        // Views and hosts call these — the mark only closes the browser path.
        $component = Livewire::test(SearchDropdown::class)->instance();

        $this->assertSame('123.456.789-09', $component->formatValue('12345678909', 'cpf'));
    }

    #[Test]
    public function every_public_method_that_returns_data_is_marked_server_only(): void
    {
        $unmarked = [];

        foreach ([BaseCrud::class, SearchDropdown::class] as $class) {
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                if (str_starts_with($m->getDeclaringClass()->getName(), 'Livewire') || $m->isStatic()
                    || str_starts_with($m->getName(), '_') || in_array($m->getName(), self::CLIENT_RETURNING, true)) {
                    continue;
                }

                if ((string) $m->getReturnType() !== 'void' && $m->getAttributes(ServerOnly::class) === []) {
                    $unmarked[] = class_basename($class).'::'.$m->getName();
                }
            }
        }

        $this->assertSame([], $unmarked, "Metodo publico que devolve dados e o navegador pode chamar com argumentos escolhidos por ele:\n  "
            .implode("\n  ", $unmarked)
            ."\nMarque com #[\\Ptah\\Support\\ServerOnly] (continua publico para views e PHP), ou torne protected.");
    }
}
