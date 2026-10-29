<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\ProfilePhoto;
use Ptah\Support\UpgradeInspector;
use Ptah\Tests\TestCase;

class L155PhotoUser extends Model
{
    protected $guarded = [];
}

class L155JetstreamUser extends Model
{
    protected $guarded = [];

    public function getProfilePhotoUrlAttribute(): string
    {
        return 'https://cdn.example.com/u/'.$this->id.'.jpg';
    }
}

/**
 * Laudo 1.55.0: plain-form fields read old()/$errors, forge-select takes
 * options from outside, reviewed overrides go silent, the profile photo URL.
 */
class Laudo155ComponentsTest extends TestCase
{
    /** old() reads the REQUEST's session; a test request has none attached. */
    private function oldInput(array $input): void
    {
        session()->put('_old_input', $input);
        request()->setLaravelSession(session()->driver());
    }

    private function shareErrors(array $messages): void
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag($messages)));
    }

    #[Test]
    public function a_plain_forge_input_reads_old_and_errors_by_name(): void
    {
        $this->oldInput(['email' => 'velho@exemplo.com', 'itens' => [['qtd' => '7']]]);
        $this->shareErrors(['email' => ['E-mail inválido.']]);

        $html = (string) $this->blade('<x-forge-input label="E-mail" name="email" value="novo@exemplo.com" />');
        $this->assertMatchesRegularExpression('/value="velho@exemplo.com"/', $html);
        $this->assertStringContainsString('E-mail inválido.', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);

        $this->assertStringContainsString('value="7"', (string) $this->blade('<x-forge-input name="itens[0][qtd]" />'));

        // With wire:model, Livewire owns the value and the errors.
        $wired = (string) $this->blade('<x-forge-input name="email" wire:model="email" />');
        $this->assertStringNotContainsString('velho@exemplo.com', $wired);
        $this->assertStringNotContainsString('E-mail inválido.', $wired);
    }

    #[Test]
    public function an_explicit_error_and_a_password_are_left_alone(): void
    {
        $this->oldInput(['senha' => 'segredo']);
        $this->shareErrors(['email' => ['Do bag.']]);

        $this->assertStringNotContainsString('segredo', (string) $this->blade('<x-forge-input type="password" name="senha" />'));
        $html = (string) $this->blade('<x-forge-input name="email" error="Explícito." />');
        $this->assertStringContainsString('Explícito.', $html);
        $this->assertStringNotContainsString('Do bag.', $html);
    }

    #[Test]
    public function a_plain_forge_select_reads_old_and_errors_and_listens_for_options(): void
    {
        $this->oldInput(['status' => 'b', 'tags' => ['a', 'b']]);
        $this->shareErrors(['status' => ['Escolha um status.']]);
        $options = [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']];

        $html = (string) $this->blade('<x-forge-select name="status" :options="$o" />', ['o' => $options]);
        $this->assertStringContainsString("selected: 'b'", $html);
        $this->assertStringContainsString('Escolha um status.', $html);
        $this->assertStringContainsString('forge-select-options.window', $html);

        $multiple = (string) $this->blade('<x-forge-select name="tags" multiple :options="$o" />', ['o' => $options]);
        $this->assertStringContainsString('selected: '.Js::from(['a', 'b']), $multiple);
    }

    #[Test]
    public function a_reviewed_override_is_silent_until_the_package_view_changes(): void
    {
        $relative = 'components/forge-empty.blade.php';
        $package = dirname(__DIR__, 3).'/resources/views/'.$relative;
        $published = resource_path('views/vendor/ptah/'.$relative);
        @mkdir(dirname($published), 0755, true);
        $hash = UpgradeInspector::viewHash($package);

        try {
            file_put_contents($published, "{{-- ptah:intentional-override:{$hash} --}}\nminha versão");
            $this->assertSame([], UpgradeInspector::views(dirname(__DIR__, 3)));

            file_put_contents($published, "{{-- ptah:intentional-override:000000000000 --}}\nminha versão");
            $found = UpgradeInspector::views(dirname(__DIR__, 3));
            $this->assertSame('warn', $found[0]['level'] ?? null);
            $this->assertStringContainsString("now {$hash}", $found[0]['message']);

            file_put_contents($published, "{{-- ptah:intentional-override --}}\nminha versão");
            $found = UpgradeInspector::views(dirname(__DIR__, 3));
            $this->assertSame('info', $found[0]['level'] ?? null);
            $this->assertStringContainsString("hash {$hash}", $found[0]['message']);
        } finally {
            @unlink($published);
        }
    }

    #[Test]
    public function the_profile_photo_url_comes_from_the_model_or_the_configured_disk(): void
    {
        $this->assertSame('https://cdn.example.com/u/5.jpg', ProfilePhoto::url(new L155JetstreamUser(['id' => 5])));

        $this->assertNull(ProfilePhoto::url(new L155PhotoUser));
        $this->assertSame('/upload/a.jpg', ProfilePhoto::url(new L155PhotoUser(['profile_photo_path' => '/upload/a.jpg'])));

        config()->set('filesystems.disks.avatars', ['driver' => 'local', 'root' => sys_get_temp_dir(), 'url' => '/avatars']);
        config()->set('ptah.auth.profile_photo_disk', 'avatars');
        $this->assertSame('/avatars/p.jpg', ProfilePhoto::url(new L155PhotoUser(['profile_photo_path' => 'p.jpg'])));
    }
}
