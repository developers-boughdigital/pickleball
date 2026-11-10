<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suburbs with their postcode, council (LGA) and city or region, loaded from
 * database/data/localities.csv by `php artisan localities:import`. Fills the
 * four geography levels on venues and clubs and gives each area a centre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('localities', function (Blueprint $table) {
            $table->id();
            $table->string('postcode', 4);
            $table->string('suburb', 100);
            $table->string('state', 3);
            $table->string('council', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->unique(['postcode', 'suburb']);
            $table->index(['state', 'city', 'council']);
        });

        Schema::create('listing_reports', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30); // new_court, incorrect_details
            $table->nullableUuidMorphs('reportable');
            $table->string('state', 3)->nullable();
            $table->string('area', 150)->nullable();
            $table->string('name', 100)->nullable();
            $table->string('email')->nullable();
            $table->text('details');
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_reports');
        Schema::dropIfExists('localities');
    }
};
