<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Columns follow clubs.csv from the directory import pack (see files/IMPORT_GUIDE.md).
 * levels and session_types are pipe-separated in the file and kept as JSON arrays.
 * enrichment_evidence is admin-only: never show it on public pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ref', 20)->nullable()->unique();
            $table->string('slug')->unique();
            $table->string('name');

            $table->string('suburb')->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('council')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 3)->nullable()->index();

            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable();
            $table->json('levels')->nullable();
            $table->json('session_types')->nullable();
            $table->text('session_times_text')->nullable();
            foreach (['beginners_welcome', 'juniors', 'seniors', 'womens_sessions', 'equipment_provided', 'coaching_available', 'come_and_try', 'dupr_play', 'pa_affiliated'] as $feature) {
                $table->boolean($feature)->nullable();
            }
            $table->string('state_affiliation', 100)->nullable();

            $table->unsignedInteger('membership_fee_cents')->nullable();
            $table->unsignedInteger('casual_fee_cents')->nullable();
            $table->unsignedInteger('court_hire_cents')->nullable();
            $table->text('membership_notes')->nullable();
            $table->string('booking_method', 20)->nullable();
            $table->string('booking_url')->nullable();
            $table->string('calendar_url')->nullable();
            $table->text('coach_names_text')->nullable();
            $table->text('leagues_text')->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('youtube')->nullable();
            $table->unsignedSmallInteger('year_established')->nullable();
            $table->unsignedInteger('member_count')->nullable();
            $table->text('enrichment_evidence')->nullable();

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

        // club_venue.csv: which venues each club plays at.
        Schema::create('club_venue', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('club_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('venue_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['club_id', 'venue_id']);
            $table->index('venue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_venue');
        Schema::dropIfExists('clubs');
    }
};
