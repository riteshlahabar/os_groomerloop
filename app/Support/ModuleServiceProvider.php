<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Base provider every feature module extends (D-007).
 *
 * A module is a self-contained folder under modules/ holding its own models, actions,
 * controllers, requests, policies, migrations, routes, views and tests. This class wires
 * those conventional subfolders up so an individual module's provider stays close to empty
 * and only declares what is genuinely specific to it.
 *
 * Container bindings are NOT reimplemented here: Laravel's own ServiceProvider already
 * registers its public $bindings and $singletons arrays, so a module declares its
 * interface-to-implementation map on those inherited properties.
 *
 * Conventional layout, all optional:
 *
 *   Routes/api.php            -> /api/v1/*, "api" middleware group, route names api.v1.*
 *   Routes/web.php            -> web middleware group, no prefix (webhooks, public pages)
 *   Database/Migrations/      -> auto-loaded, no path registration needed
 *   Resources/views/          -> view('<module>::...'), used for mail templates
 *   Resources/lang/           -> trans('<module>::...')
 *   Config/<module>.php       -> config('<module>.*'), merged so app config wins
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * The API version every module's routes are published under.
     *
     * Spec §33 requires a versioned API. Bumping this is a deliberate, breaking act.
     */
    protected const API_PREFIX = 'api/v1';

    protected const API_ROUTE_NAME_PREFIX = 'api.v1.';

    /**
     * Absolute path to the module directory, resolved once from the provider's own file.
     */
    private ?string $resolvedPath = null;

    private ?string $resolvedName = null;

    public function register(): void
    {
        $this->registerModuleConfig();
    }

    public function boot(): void
    {
        $this->registerModuleMigrations();
        $this->registerModuleTranslations();
        $this->registerModuleViews();
        $this->registerModuleRoutes();
    }

    /**
     * The module's short name, e.g. "Scheduling" for Modules\Scheduling\SchedulingServiceProvider.
     */
    protected function moduleName(): string
    {
        if ($this->resolvedName === null) {
            $segments = explode('\\', static::class);

            // Modules \ <Name> \ <Name>ServiceProvider
            $this->resolvedName = $segments[1] ?? 'Unknown';
        }

        return $this->resolvedName;
    }

    /**
     * The namespace used for this module's views, translations and config keys.
     */
    protected function moduleNamespace(): string
    {
        return Str::snake($this->moduleName());
    }

    /**
     * Absolute path inside the module directory.
     */
    protected function modulePath(string $append = ''): string
    {
        if ($this->resolvedPath === null) {
            $this->resolvedPath = dirname((new ReflectionClass(static::class))->getFileName());
        }

        return $append === ''
            ? $this->resolvedPath
            : $this->resolvedPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $append);
    }

    private function registerModuleConfig(): void
    {
        $file = $this->modulePath('Config/'.$this->moduleNamespace().'.php');

        if (is_file($file)) {
            $this->mergeConfigFrom($file, $this->moduleNamespace());
        }
    }

    private function registerModuleMigrations(): void
    {
        $path = $this->modulePath('Database/Migrations');

        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }
    }

    private function registerModuleTranslations(): void
    {
        $path = $this->modulePath('Resources/lang');

        if (is_dir($path)) {
            $this->loadTranslationsFrom($path, $this->moduleNamespace());
        }
    }

    private function registerModuleViews(): void
    {
        $path = $this->modulePath('Resources/views');

        if (is_dir($path)) {
            $this->loadViewsFrom($path, $this->moduleNamespace());
        }
    }

    /**
     * Register the module's route files.
     *
     * Skipped entirely when routes are cached, so a warm production boot never touches
     * the filesystem for routing.
     */
    private function registerModuleRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $api = $this->modulePath('Routes/api.php');

        if (is_file($api)) {
            Route::middleware('api')
                ->prefix(self::API_PREFIX)
                ->name(self::API_ROUTE_NAME_PREFIX)
                ->group($api);
        }

        $web = $this->modulePath('Routes/web.php');

        if (is_file($web)) {
            Route::middleware('web')->group($web);
        }
    }
}
