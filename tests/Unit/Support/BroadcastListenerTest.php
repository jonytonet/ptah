<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\BroadcastListener;
use Ptah\Tests\TestCase;

/**
 * The Echo listener a screen registers. Until 1.39.0 it was always a public
 * `echo:` channel — subscribable by anyone with the websocket key, which ships
 * in the page (achado #12 do PetPlace).
 */
class BroadcastListenerTest extends TestCase
{
    #[Test]
    public function off_registers_nothing(): void
    {
        $this->assertNull(BroadcastListener::key(['enabled' => false], 'Catalog/Product'));
    }

    #[Test]
    public function a_private_channel_uses_echo_private(): void
    {
        $this->assertSame(
            'echo-private:page-product-observer,.pageProductObserver',
            BroadcastListener::key(['enabled' => true, 'type' => 'private'], 'Catalog/Product')
        );
        $this->assertSame(
            'echo-presence:page-product-observer,.pageProductObserver',
            BroadcastListener::key(['enabled' => true, 'type' => 'presence'], 'Catalog/Product')
        );
    }

    #[Test]
    public function per_company_appends_the_active_company(): void
    {
        $this->assertSame(
            'echo-private:page-product-observer.7,.pageProductObserver',
            BroadcastListener::key(['enabled' => true, 'type' => 'private', 'perCompany' => true], 'Catalog/Product', 7)
        );
        $this->assertSame(
            'echo-private:page-product-observer,.pageProductObserver',
            BroadcastListener::key(['enabled' => true, 'type' => 'private', 'perCompany' => true], 'Catalog/Product', 0),
            'Sem empresa ativa, sem sufixo.'
        );
    }

    #[Test]
    public function existing_configs_keep_their_public_channel_and_an_unknown_type_is_public(): void
    {
        // Mudar o padrao quebraria quem ja escuta `echo:` — e o editor sugere privado.
        $this->assertSame('echo:page-product-observer,.pageProductObserver', BroadcastListener::key(['enabled' => true], 'Product'));
        $this->assertSame('public', BroadcastListener::type(['type' => 'bogus']));
        $this->assertSame('private', BroadcastListener::type(['private' => true]));
    }

    #[Test]
    public function the_screen_registers_the_same_listener(): void
    {
        CrudConfig::create(['model' => 'Catalog/Product', 'route' => '', 'config' => [
            'cols' => [], 'broadcast' => ['enabled' => true, 'type' => 'private', 'perCompany' => true],
        ]]);
        session(['ptah_company_id' => 3]);

        $crud = Livewire::test(BaseCrud::class, ['model' => 'Catalog/Product'])->instance();

        $this->assertArrayHasKey('echo-private:page-product-observer.3,.pageProductObserver', $crud->getListeners());
    }
}
