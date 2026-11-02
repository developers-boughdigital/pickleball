<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Columns follow venues.csv from the directory import pack (see files/IMPORT_GUIDE.md),
 * so a re-import matches field for field. Differences from pickleball_db2.pdf:
 * - latitude/longitude instead of location geography(point) (PostGIS isn't installed)
 * - indoor_outdoor (indoor, outdoor, both) instead of an indoor boolean
 * - each facility is its own nullable boolean: NULL means unknown
 * - ref keeps the directory's ID (V0001); imports match on it
 * - status is the publishing state (draft/published); record_status is the VA's check
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ref', 20)->nullable()->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->boolean('name_needs_confirming')->default(false);
            $table->string('venue_type', 30)->nullable();

            $table->string('street_address')->nullable();
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
            $table->string('surface', 30)->nullable();
            $table->string('court_lines', 40)->nullable();
            foreach (['nets', 'lights', 'covered', 'nets_provided', 'free_to_use'] as $facility) {
                $table->boolean($facility)->nullable();
            }

            $table->unsignedInteger('court_hire_cents')->nullable();
            $table->text('price_notes')->nullable();
            $table->boolean('booking_required')->nullable();
            $table->string('booking_url')->nullable();
            $table->text('opening_hours_text')->nullable();
            foreach (['accessible', 'toilets', 'parking', 'changing_rooms', 'cafe_kiosk', 'paddle_hire'] as $facility) {
                $table->boolean($facility)->nullable();
            }
            $table->string('website')->nullable();
            $table->string('phone', 30)->nullable();
            $table->json('photos')->nullable();

            $table->string('source_type')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('record_status', 20)->default('to_check')->index();
            $table->date('checked_at')->nullable();
            $table->string('checked_by', 100)->nullable();
            $table->unsignedTinyInteger('profile_tier')->default(1);
            $table->string('claim_status', 20)->default('not_contacted');
            $table->date('enriched_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft')->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
