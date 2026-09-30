<?php

namespace Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Tests\TestCase;

/**
 * Every module folder is actually registered, and actually loaded.
 *
 * CLAUDE.md says modules are listed explicitly in bootstrap/providers.php so "an unregistered
 * module fails visibly rather than half-loading". Building Billing proved that is not true on
 * its own: the provider was added to the array but its `use` import was not, so
 * `BillingServiceProvider::class` resolved to the string "BillingServiceProvider" in the
 * global namespace. Nothing threw. The module simply did not exist — no routes, no config,
 * and `php artisan migrate` cheerfully reported "Nothing to migrate" while three tables were
 * missing.
 *
 * That is the exact failure the explicit list was supposed to prevent, so this test makes the
 * guarantee real rather than intended.
 */
final class ModuleRegistrationGuardTest extends TestCase
{
    public function test_every_module_has_its_provider_registered(): void
    {
        $registered = require base_path('bootstrap/providers.php');

        $missing = [];

        foreach ($this->moduleNames() as $module) {
            $provider = "Modules\\{$module}\\{$module}ServiceProvider";

            if (! in_array($provider, $registered, strict: true)) {
                $missing[] = $provider;
            }
        }

        $this->assertSame([], $missing, sprintf(
            "These modules exist on disk but are not listed in bootstrap/providers.php, so\n"
            ."their routes, migrations and config are silently absent:\n  - %s",
            implode("\n  - ", $missing)
        ));
    }

    /**
     * The half-load case: a fully-qualified name in the array that does not resolve.
     *
     * Checking class_exists() rather than only membership is the part that catches a missing
     * `use` statement, because the array entry looks perfectly reasonable either way.
     */
    public function test_every_registered_provider_resolves_to_a_real_class(): void
    {
        $registered = require base_path('bootstrap/providers.php');

        $broken = [];

        foreach ($registered as $provider) {
            if (! class_exists($provider) || ! is_subclass_of($provider, ServiceProvider::class)) {
                $broken[] = $provider;
            }
        }

        $this->assertSame([], $broken, sprintf(
            "These entries in bootstrap/providers.php do not resolve to a service provider.\n"
            ."A missing `use` import is the usual cause — X::class then silently becomes the\n"
            ."string \"X\" in the global namespace.\n  - %s",
            implode("\n  - ", $broken)
        ));
    }

    public function test_every_module_provider_is_actually_booted(): void
    {
        $loaded = array_keys($this->app->getLoadedProviders());

        $notBooted = [];

        foreach ($this->moduleNames() as $module) {
            $provider = "Modules\\{$module}\\{$module}ServiceProvider";

            if (! in_array($provider, $loaded, strict: true)) {
                $notBooted[] = $provider;
            }
        }

        $this->assertSame([], $notBooted, sprintf(
            "These module providers are registered but were not loaded by the application:\n  - %s",
            implode("\n  - ", $notBooted)
        ));
    }

    /**
     * Guard the guard: if the glob breaks, everything above passes by checking nothing.
     */
    public function test_the_guard_discovers_the_modules_that_exist(): void
    {
        $modules = $this->moduleNames();

        $this->assertNotEmpty($modules, 'No module folders were discovered, so this guard is not looking.');
        $this->assertContains('Tenancy', $modules);
    }

    /**
     * @return list<string>
     */
    private function moduleNames(): array
    {
        $names = [];

        foreach (glob(base_path('modules/*'), GLOB_ONLYDIR) ?: [] as $path) {
            $names[] = basename($path);
        }

        sort($names);

        return $names;
    }
}
