<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\BoostPackages;
use Ptah\Support\UpgradeInspector;
use Ptah\Tests\TestCase;

/**
 * Laudo 1.53.0, outside the auth guard: the navbar without the company module,
 * plain-form fields, the 2FA migration on a Fortify project, and Boost.
 */
class Laudo153FormsTest extends TestCase
{
    #[Test]
    public function the_navbar_renders_without_the_company_module(): void
    {
        config(['ptah.modules.company' => false]);
        $userClass = config('auth.providers.users.model');
        $user = $userClass::forceCreate(['name' => 'N', 'email' => 'n'.uniqid().'@test.com', 'password' => bcrypt('x')]);
        Auth::loginUsingId($user->id);

        $html = (string) $this->blade('<x-forge-navbar appName="Test" />');

        $this->assertStringContainsString('Test', $html);
        $this->assertStringNotContainsString('company-switcher', $html);
    }

    #[Test]
    public function forge_input_writes_its_value_for_a_plain_form(): void
    {
        $html = (string) $this->blade('<x-forge-input name="email" value="ana@example.com" />');

        $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*value="ana@example.com"/s', $html);
    }

    #[Test]
    public function forge_select_posts_its_name_in_a_plain_form(): void
    {
        $options = [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']];

        $single = (string) $this->blade('<x-forge-select name="status" :options="$o" />', ['o' => $options]);
        $this->assertMatchesRegularExpression('/<input type="hidden"[^>]*name="status"/s', $single);

        $multiple = (string) $this->blade('<x-forge-select name="tags" multiple :options="$o" />', ['o' => $options]);
        $this->assertStringContainsString('name="tags[]"', $multiple);
        $this->assertDoesNotMatchRegularExpression('/name="tags"[^\[]/', $multiple);
    }

    #[Test]
    public function the_2fa_migration_runs_on_a_table_that_already_has_the_columns(): void
    {
        // The test users table already went through it once: every column exists,
        // which is the Fortify/Jetstream situation.
        $this->assertTrue(Schema::hasColumn('users', 'two_factor_secret'));

        $migration = require dirname(__DIR__, 3).'/src/Migrations/2024_01_03_000001_add_two_factor_columns_to_users_table.php';
        $migration->up();

        $this->assertTrue(Schema::hasColumns('users', ['two_factor_secret', 'two_factor_type', 'profile_photo_path']));
    }

    #[Test]
    public function boost_json_gets_ptah_and_upgrade_check_reports_when_it_lacks_it(): void
    {
        $path = BoostPackages::path();
        $backup = is_file($path) ? file_get_contents($path) : null;

        try {
            file_put_contents($path, json_encode(['guidelines' => true, 'packages' => ['acme/other']]));
            $this->assertFalse(BoostPackages::includesPtah());
            $this->assertSame('boost', UpgradeInspector::boost()[0]['check'] ?? null);

            $this->assertTrue(BoostPackages::includePtah());
            $config = json_decode((string) file_get_contents($path), true);
            $this->assertSame(['acme/other', 'jonytonet/ptah'], $config['packages']);
            $this->assertTrue($config['guidelines']);
            $this->assertSame([], UpgradeInspector::boost());

            // Twice is still once.
            BoostPackages::includePtah();
            $this->assertSame(['acme/other', 'jonytonet/ptah'], json_decode((string) file_get_contents($path), true)['packages']);

            // No boost.json: nothing to report (Boost is not set up).
            unlink($path);
            $this->assertSame([], UpgradeInspector::boost());
        } finally {
            $backup === null ? @unlink($path) : file_put_contents($path, $backup);
        }
    }
}
