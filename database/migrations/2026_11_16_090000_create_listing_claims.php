<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Claiming a listing (project plan steps 5, 13 and 14):
 * - listing_status on venues and clubs: unclaimed, pending_claim, claimed, suspended.
 * - listing_claims, as in pickleball_db2: who asked, the role they stated, their
 *   evidence, and the platform admin's decision.
 * - listing_roles: who runs a club (admin, host, coach, treasurer) or manages a
 *   venue. Approving a claim adds the first one.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['venues', 'clubs'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('listing_status', 20)->default('unclaimed')->index()->after('claim_status');
            });

            // Listings the outreach already marked as claimed keep that.
            DB::table($table)->whereIn('claim_status', ['claimed', 'profile_completed'])->update(['listing_status' => 'claimed']);
        }

        Schema::create('listing_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('claimable');
            $table->string('role_stated', 100);
            $table->text('evidence');
            $table->string('status', 20)->default('pending')->index(); // pending, approved, rejected, withdrawn
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('listing_roles', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('listing');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20); // clubs: admin, host, coach, treasurer. Venues: manager.
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['listing_type', 'listing_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_roles');
        Schema::dropIfExists('listing_claims');

        foreach (['venues', 'clubs'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropIndex(['listing_status']);
                $table->dropColumn('listing_status');
            });
        }
    }
};
