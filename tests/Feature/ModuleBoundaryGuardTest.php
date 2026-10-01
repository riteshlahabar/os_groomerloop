<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * CI guard 3 (D-007): a module reaches another module only through its Contracts, or through a
 * domain event. Never another module's Eloquent model.
 *
 * This guard is written now because Phase 5 is where it starts to matter. Crm is the first
 * module other modules will genuinely want to reach into: Pets needs to know a customer exists,
 * Scheduling needs a name for the calendar, Notifications needs to know whether it may send
 * anything at all. Each of those is one `use Modules\Crm\Models\Customer;` away from turning
 * the modular monolith into a single tangled namespace — and consent in particular would then
 * have three implementations instead of one (invariant #9).
 *
 * Like ModelTenancyGuardTest, this scans the tree rather than checking a list someone has to
 * remember to update.
 */
final class ModuleBoundaryGuardTest extends TestCase
{
    /**
     * Modules that are shared kernel, and may therefore be depended on by everyone.
     *
     * Tenancy owns the Tenant every other table hangs off, and Audit owns the event log every
     * module writes to. Both are infrastructure rather than features, which is exactly why
     * CLAUDE.md names them as the exception.
     *
     * @var list<string>
     */
    private const SHARED_KERNEL = ['Tenancy', 'Audit'];

    /**
     * Crossings that exist and are accepted, named file by file so each one had to be argued
     * for rather than quietly permitted by a broad rule.
     *
     * A factory is test-data scaffolding, not a runtime code path: nothing a customer's request
     * touches goes through it. The coupling is still recorded here so it is visible, and so
     * that adding a second one is a deliberate act.
     *
     * @var array<string, string>
     */
    private const ACCEPTED = [
        'Billing/Database/Factories/SubscriptionFactory.php' => 'Modules\Entitlements\Models\Plan',
    ];

    public function test_no_module_reaches_into_another_modules_models(): void
    {
        $offenders = [];

        foreach ($this->modulePhpFiles() as $relative => $path) {
            $owningModule = explode('/', $relative)[0];
            $source = (string) file_get_contents($path);

            preg_match_all('/Modules\\\\+([A-Za-z0-9_]+)\\\\+Models\\\\+([A-Za-z0-9_]+)/', $source, $matches);

            foreach ($matches[1] as $i => $referencedModule) {
                $reference = 'Modules\\'.$referencedModule.'\\Models\\'.$matches[2][$i];

                if ($referencedModule === $owningModule) {
                    continue;
                }

                if (in_array($referencedModule, self::SHARED_KERNEL, true)) {
                    continue;
                }

                if ((self::ACCEPTED[$relative] ?? null) === $reference) {
                    continue;
                }

                $offenders[] = $relative.' references '.$reference;
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)), implode("\n", [
            'A module is reaching into another module\'s models (D-007).',
            '',
            'Go through the owning module\'s Contracts/ interface instead, or have it publish a',
            'domain event. If the other module has no contract for what you need, add one — that',
            'is the boundary doing its job, not an obstacle to route around.',
            '',
            ...array_unique($offenders),
        ]));
    }

    /**
     * Proves the guard can actually see a violation, rather than passing because the regex
     * never matches anything. The Phase 1 lesson: a guard nobody has watched fail is a guard
     * that might be green for the wrong reason.
     */
    public function test_the_guard_recognises_a_violation(): void
    {
        $sample = '<?php use Modules\Crm\Models\Customer;';

        preg_match_all('/Modules\\\\+([A-Za-z0-9_]+)\\\\+Models\\\\+([A-Za-z0-9_]+)/', $sample, $matches);

        $this->assertSame(['Crm'], $matches[1]);
        $this->assertSame(['Customer'], $matches[2]);
    }

    /**
     * Every module's PHP, excluding its own tests — a test may legitimately build another
     * module's records to set a scenario up.
     *
     * @return array<string, string>
     */
    private function modulePhpFiles(): array
    {
        $root = base_path('modules');
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

            if (str_contains($relative, '/Tests/')) {
                continue;
            }

            $files[$relative] = $file->getPathname();
        }

        return $files;
    }
}
