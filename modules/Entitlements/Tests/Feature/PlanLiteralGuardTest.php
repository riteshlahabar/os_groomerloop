<?php

namespace Modules\Entitlements\Tests\Feature;

use Tests\TestCase;

/**
 * CI guard #4 (see CLAUDE.md, "Build sequence"): no plan-name literal outside the seeder.
 *
 * Invariant #3 says plan gating goes through one entitlement service and a plan name never
 * appears in a controller, view or policy. That is easy to state and easy to break — the
 * shortest path to shipping a feature is `if ($tenant->plan->key === 'growth')`, and it works,
 * and it is invisible in review until packaging changes and a dozen such checks have to be
 * found by hand.
 *
 * So this test greps the application for the four plan keys and the four prices, and fails
 * naming the file. It is the structural twin of ModelTenancyGuardTest.
 *
 * Two exemptions, both deliberate:
 *   - PlanSeeder, which is where the catalog is defined.
 *   - Tests, which have to be able to assert that "growth_partner" grants what §25 says it
 *     grants. The guard protects application logic from branching on a plan; a test naming a
 *     plan is the thing doing the protecting.
 */
final class PlanLiteralGuardTest extends TestCase
{
    /**
     * Plan keys that cannot mean anything else, banned as quoted literals anywhere.
     */
    private const UNAMBIGUOUS_KEYS = ['starter', 'growth_partner'];

    /**
     * Plan keys that collide with the product's ordinary vocabulary.
     *
     * "business" is the domain's central noun — it is a slug fallback in RegisterBusiness and
     * a JSON field name in UserResource, both entirely legitimate — and "growth" names a
     * navigation section and a permission. Banning them outright produced three false
     * positives on the first run and would train everyone to ignore this test.
     *
     * So they are flagged only on a line that also talks about plans. That still catches the
     * mistake this guard exists for, `$tenant->plan->key === 'growth'`, while leaving
     * `'business' => [...]` alone.
     */
    private const CONTEXTUAL_KEYS = ['business', 'growth'];

    /**
     * Words that turn a contextual key into a plan reference.
     */
    private const PLAN_CONTEXT = ['plan', 'tier', 'subscription', 'entitle'];

    /**
     * Spec §2 prices in cents, with and without PHP's numeric separator.
     */
    private const PLAN_PRICES = ['7900', '7_900', '14900', '14_900', '24900', '24_900', '39900', '39_900'];

    public function test_no_plan_name_appears_outside_the_seeder(): void
    {
        $offenders = $this->scanForPlanNames();

        $this->assertSame([], $offenders, sprintf(
            "These files name a plan. Plan packaging is data (invariant #3): ask the\n"
            ."Entitlements contract for a Feature instead of branching on a plan.\n  - %s",
            implode("\n  - ", $offenders)
        ));
    }

    public function test_no_plan_price_appears_outside_the_seeder(): void
    {
        $pattern = '/\b('.implode('|', self::PLAN_PRICES).')\b/';

        $offenders = $this->scan($pattern);

        $this->assertSame([], $offenders, sprintf(
            "These files hard-code a plan price. Prices live in the plans table so §2\n"
            ."packaging can change without a deploy.\n  - %s",
            implode("\n  - ", $offenders)
        ));
    }

    /**
     * Guard the guard: prove the scanner actually reads files and would catch an offender.
     *
     * Without this, a broken glob would make both tests above pass by scanning nothing —
     * exactly the failure mode ModelTenancyGuardTest was deliberately broken to rule out.
     */
    public function test_the_scanner_finds_the_seeder_when_the_seeder_is_not_exempt(): void
    {
        $hits = $this->scanForPlanNames(exemptSeeder: false);

        $this->assertContains(
            'modules/Entitlements/Database/Seeders/PlanSeeder.php',
            $hits,
            'The scanner did not find plan names in the one file guaranteed to contain them, '
            .'so it is not actually looking at the codebase.'
        );
    }

    /**
     * Flag files naming a plan: unambiguous keys anywhere, contextual keys only on a line
     * that also talks about plans.
     *
     * @return list<string> repo-relative paths, sorted
     */
    private function scanForPlanNames(bool $exemptSeeder = true): array
    {
        $unambiguous = '/([\'"])('.implode('|', self::UNAMBIGUOUS_KEYS).')\1/i';
        $contextual = '/([\'"])('.implode('|', self::CONTEXTUAL_KEYS).')\1/i';
        $context = '/('.implode('|', self::PLAN_CONTEXT).')/i';

        $offenders = [];

        foreach ($this->scannableFiles($exemptSeeder) as $relative => $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            if (preg_match($unambiguous, $contents) === 1) {
                $offenders[] = $relative;

                continue;
            }

            foreach (preg_split('/\R/', $contents) ?: [] as $line) {
                if (preg_match($contextual, $line) === 1 && preg_match($context, $line) === 1) {
                    $offenders[] = $relative;

                    break;
                }
            }
        }

        sort($offenders);

        return array_values(array_unique($offenders));
    }

    /**
     * @return list<string> repo-relative paths, sorted
     */
    private function scan(string $pattern, bool $exemptSeeder = true): array
    {
        $offenders = [];

        foreach ($this->scannableFiles($exemptSeeder) as $relative => $file) {
            $contents = file_get_contents($file);

            if ($contents !== false && preg_match($pattern, $contents) === 1) {
                $offenders[] = $relative;
            }
        }

        sort($offenders);

        return $offenders;
    }

    /**
     * Application PHP files the guard applies to, keyed by repo-relative path.
     *
     * @return array<string, string>
     */
    private function scannableFiles(bool $exemptSeeder): array
    {
        $files = [];

        foreach ($this->sourceFiles() as $file) {
            $relative = $this->relative($file);

            if ($exemptSeeder && str_contains($relative, 'Database/Seeders/PlanSeeder.php')) {
                continue;
            }

            // Tests are allowed to name plans — see the class docblock.
            if ($this->isTest($relative)) {
                continue;
            }

            $files[$relative] = $file;
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];

        foreach ([base_path('app'), base_path('modules'), base_path('database')] as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function isTest(string $relative): bool
    {
        return str_contains($relative, '/Tests/') || str_starts_with($relative, 'tests/');
    }

    private function relative(string $path): string
    {
        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

        return str_replace('\\', '/', $relative);
    }
}
