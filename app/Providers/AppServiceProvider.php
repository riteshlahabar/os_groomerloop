<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->enforceModelDiscipline();
        $this->protectProductionDatabase();
        $this->resolveModulePolicies();
        $this->setPasswordPolicy();
    }

    /**
     * Fail loudly in development on the mistakes that quietly ruin performance in production.
     *
     * shouldBeStrict() turns lazy loading (the usual cause of N+1 queries), assigning to an
     * unfillable attribute, and reading a missing attribute into exceptions. Catching these
     * during Phase 5-7 development is far cheaper than finding them on a tenant's calendar,
     * which is why it is on from the first phase rather than added in a later tuning pass.
     */
    private function enforceModelDiscipline(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Refuse commands that would wipe tenant data on a production database (spec §33).
     */
    private function protectProductionDatabase(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    /**
     * Teach Laravel where a module keeps its policies.
     *
     * The framework's default guesser only understands App\Models\Foo => App\Policies\Foo.
     * Under D-007 a model lives at Modules\Crm\Models\Customer and its policy belongs
     * beside it at Modules\Crm\Policies\CustomerPolicy, so authorization stays inside the
     * module that owns the record.
     */
    private function resolveModulePolicies(): void
    {
        Gate::guessPolicyNamesUsing(static function (string $model): array {
            $candidates = [];

            if (str_contains($model, '\\Models\\')) {
                $candidates[] = str_replace('\\Models\\', '\\Policies\\', $model).'Policy';
            }

            $segments = explode('\\', $model);
            $class = array_pop($segments);
            $candidates[] = implode('\\', $segments).'\\Policies\\'.$class.'Policy';

            return array_values(array_unique($candidates));
        });
    }

    /**
     * One password policy for the whole product, not a per-form guess.
     *
     * Twelve characters with a mixed case and a number is the floor; in production every
     * password is additionally checked against known breach corpora, because a groomer
     * reusing a leaked password is the most likely route into a tenant's customer list.
     */
    private function setPasswordPolicy(): void
    {
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->uncompromised()
            : Password::min(12)->mixedCase()->numbers());
    }
}
