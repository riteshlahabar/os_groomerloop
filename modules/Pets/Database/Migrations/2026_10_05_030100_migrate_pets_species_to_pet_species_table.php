<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Every tenant gets the same three starting rows the old `PetSpecies` enum offered (Dog,
     * Cat, Other) — nobody loses an option they had before. Existing `pets.species` values are
     * exactly `dog`/`cat`/`other`, which are also the slugs these seeded rows get, so the
     * backfill is a plain join on slug rather than a per-tenant PHP loop.
     */
    private const DEFAULTS = ['Dog', 'Cat', 'Other'];

    public function up(): void
    {
        $now = now();

        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            foreach (self::DEFAULTS as $index => $name) {
                DB::table('pet_species')->insertOrIgnore([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'position' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('pets', function (Blueprint $table): void {
            $table->foreignId('species_id')->nullable()->after('species')
                ->constrained('pet_species')->restrictOnDelete();
        });

        DB::statement(
            'UPDATE pets
             INNER JOIN pet_species ON pet_species.tenant_id = pets.tenant_id AND pet_species.slug = pets.species
             SET pets.species_id = pet_species.id
             WHERE pets.species_id IS NULL'
        );

        Schema::table('pets', function (Blueprint $table): void {
            $table->unsignedBigInteger('species_id')->nullable(false)->change();
        });

        Schema::table('pets', function (Blueprint $table): void {
            $table->dropColumn('species');
        });
    }

    public function down(): void
    {
        Schema::table('pets', function (Blueprint $table): void {
            $table->string('species', 16)->nullable()->after('name');
        });

        DB::statement(
            'UPDATE pets
             INNER JOIN pet_species ON pet_species.id = pets.species_id
             SET pets.species = pet_species.slug'
        );

        Schema::table('pets', function (Blueprint $table): void {
            $table->string('species', 16)->nullable(false)->change();
            $table->dropConstrainedForeignId('species_id');
        });
    }
};
