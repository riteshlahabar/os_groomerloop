<?php

namespace Modules\Tenancy\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Tenancy\Concerns\BelongsToTenant;
use Modules\Tenancy\Models\Tenant;
use ReflectionClass;
use Tests\TestCase;

/**
 * The structural guard behind invariant #1.
 *
 * D-001 chose a shared schema, which means tenant isolation holds only if every tenant-owned
 * model applies the global scope. A single model that forgets the trait is a silent
 * cross-tenant leak that no feature test would notice, because the model would simply return
 * more rows than it should.
 *
 * So rather than trusting convention, this test discovers every model in the codebase, asks
 * the database whether its table has a tenant_id column, and fails the build if such a model
 * does not use BelongsToTenant. It costs one test and removes a whole class of mistake.
 */
final class ModelTenancyGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_model_with_a_tenant_id_column_is_tenant_scoped(): void
    {
        $checked = 0;
        $offenders = [];

        foreach ($this->discoverModels() as $class) {
            $model = new $class;
            $table = $model->getTable();

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            $checked++;

            if (! in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
                $offenders[] = $class.' (table: '.$table.')';
            }
        }

        // Guard the guard: a broken glob or a renamed directory would otherwise let this test
        // pass by checking nothing at all.
        $this->assertGreaterThan(
            0,
            $checked,
            'The guard found no tenant-owned models, which means it is not actually looking.'
        );

        $this->assertSame([], $offenders, sprintf(
            'These models own a tenant_id column but do not use BelongsToTenant, so their '
            ."queries are not tenant-scoped:\n  - %s",
            implode("\n  - ", $offenders)
        ));
    }

    public function test_the_tenant_model_itself_is_not_tenant_scoped(): void
    {
        // Scoping the tenant table by tenant would make it impossible to resolve a tenant in
        // the first place, so this is a deliberate and permanent exception.
        $this->assertNotContains(
            BelongsToTenant::class,
            class_uses_recursive(Tenant::class)
        );
    }

    /**
     * @return list<class-string<Model>>
     */
    private function discoverModels(): array
    {
        $files = array_merge(
            glob(base_path('app/Models/*.php')) ?: [],
            glob(base_path('modules/*/Models/*.php')) ?: [],
        );

        $models = [];

        foreach ($files as $file) {
            $class = $this->classFromPath($file);

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->isSubclassOf(Model::class) || $reflection->isAbstract()) {
                continue;
            }

            $models[] = $class;
        }

        return $models;
    }

    /**
     * app/Models/User.php              => App\Models\User
     * modules/Tenancy/Models/Tenant.php => Modules\Tenancy\Models\Tenant
     *
     * @return class-string
     */
    private function classFromPath(string $path): string
    {
        $relative = str_replace([base_path().DIRECTORY_SEPARATOR, base_path().'/'], '', $path);
        $relative = str_replace('\\', '/', $relative);
        $relative = preg_replace('/\.php$/', '', $relative) ?? $relative;

        $segments = explode('/', $relative);

        // Both PSR-4 roots map their first path segment to a capitalised namespace root.
        $segments[0] = ucfirst($segments[0]);

        /** @var class-string $class */
        $class = implode('\\', $segments);

        return $class;
    }
}
