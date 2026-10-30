<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Follows the venues table in pickleball_db2.pdf, with these differences:
 * - latitude/longitude instead of location geography(point) (PostGIS isn't installed)
 * - indoor_outdoor instead of indoor boolean, because the directory also has "Both"
 * - opening_hours as text instead of hours json; the directory records it as free text
 * - code keeps the directory's Venue ID (V0001) so re-imports update the same row
 * - the directory's verification columns (source, checked by/at, notes)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->nullable()->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('venue_type')->nullable();

            $table->string('address')->nullable();
            $table->string('suburb')->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('council')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 3)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_place_id')->nullable();

            $table->unsignedSmallInteger('court_count')->nullable();
            $table->string('indoor_outdoor', 10)->nullable();
            $table->string('surface')->nullable();
            $table->string('court_lines')->nullable();
            $table->boolean('lights')->nullable();
            // covered, nets_provided, free_to_use, booking_required, accessible, toilets, parking, club_codes
            $table->json('attributes')->nullable();

            $table->decimal('price_per_hour', 8, 2)->nullable();
            $table->text('price_notes')->nullable();
            $table->string('booking_url')->nullable();
            $table->string('opening_hours')->nullable();
            $table->string('website')->nullable();
            $table->string('phone', 30)->nullable();
            $table->json('photos')->nullable();

            $table->string('source_type')->nullable();
            $table->string('source_url')->nullable();
            $table->date('checked_at')->nullable();
            $table->string('checked_by')->nullable();
            $table->string('status', 20)->default('To check')->index();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
