<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\Entitlements\Database\Seeders\PlanSeeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Only reference data belongs here — rows the application cannot function without. The
     * plan catalog is exactly that: with no plans, PlanEntitlements finds no default and
     * fails closed, so every feature in the product is locked.
     *
     * Demo tenants, users, customers and appointments are deliberately absent. Seeding a fake
     * business into a database that may be production is how test data reaches customers.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
        ]);
    }
}
