<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * The create_venues migration was rewritten after it had already run on some
 * servers (code/address/attributes/price_per_hour → ref/street_address/…).
 * This brings an old venues table up to the directory import schema without
 * requiring direct DB access. Safe to run where the table is already current.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venues')) {
            return;
        }

        $this->renameIfPresent('code', 'ref');
        $this->renameIfPresent('address', 'street_address');
        $this->renameIfPresent('opening_hours', 'opening_hours_text');

        Schema::table('venues', function (Blueprint $table) {
            if (! Schema::hasColumn('venues', 'name_needs_confirming')) {
                $table->boolean('name_needs_confirming')->default(false)->after('name');
            }

            foreach (['nets', 'covered', 'nets_provided', 'free_to_use'] as $facility) {
                if (! Schema::hasColumn('venues', $facility)) {
                    $table->boolean($facility)->nullable()->after('lights');
                }
            }

            if (! Schema::hasColumn('venues', 'court_hire_cents')) {
                $table->unsignedInteger('court_hire_cents')->nullable()->after('free_to_use');
            }

            if (! Schema::hasColumn('venues', 'booking_required')) {
                $table->boolean('booking_required')->nullable()->after('price_notes');
            }

            foreach (['accessible', 'toilets', 'parking', 'changing_rooms', 'cafe_kiosk', 'paddle_hire'] as $facility) {
                if (! Schema::hasColumn('venues', $facility)) {
                    $table->boolean($facility)->nullable();
                }
            }

            if (! Schema::hasColumn('venues', 'record_status')) {
                $table->string('record_status', 20)->default('to_check')->index()->after('source_url');
            }

            if (! Schema::hasColumn('venues', 'profile_tier')) {
                $table->unsignedTinyInteger('profile_tier')->default(1)->after('checked_by');
            }

            if (! Schema::hasColumn('venues', 'claim_status')) {
                $table->string('claim_status', 20)->default('not_contacted')->after('profile_tier');
            }

            if (! Schema::hasColumn('venues', 'enriched_at')) {
                $table->date('enriched_at')->nullable()->after('claim_status');
            }
        });

        $this->movePricePerHourToCents();
        $this->expandAttributesJson();
        $this->splitLegacyStatus();

        Schema::table('venues', function (Blueprint $table) {
            if (Schema::hasColumn('venues', 'price_per_hour')) {
                $table->dropColumn('price_per_hour');
            }
            if (Schema::hasColumn('venues', 'attributes')) {
                $table->dropColumn('attributes');
            }
        });
    }

    public function down(): void
    {
        // One-way upgrade: older app code expects the previous column names.
    }

    private function renameIfPresent(string $from, string $to): void
    {
        if (Schema::hasColumn('venues', $from) && ! Schema::hasColumn('venues', $to)) {
            Schema::table('venues', function (Blueprint $table) use ($from, $to) {
                $table->renameColumn($from, $to);
            });
        }
    }

    private function movePricePerHourToCents(): void
    {
        if (! Schema::hasColumn('venues', 'price_per_hour') || ! Schema::hasColumn('venues', 'court_hire_cents')) {
            return;
        }

        DB::table('venues')
            ->whereNotNull('price_per_hour')
            ->whereNull('court_hire_cents')
            ->update(['court_hire_cents' => DB::raw('ROUND(price_per_hour * 100)')]);
    }

    private function expandAttributesJson(): void
    {
        if (! Schema::hasColumn('venues', 'attributes')) {
            return;
        }

        $facilities = ['covered', 'nets_provided', 'free_to_use', 'booking_required', 'accessible', 'toilets', 'parking', 'changing_rooms', 'cafe_kiosk', 'paddle_hire', 'nets'];

        DB::table('venues')->whereNotNull('attributes')->orderBy('id')->each(function (object $venue) use ($facilities) {
            $attrs = is_string($venue->attributes)
                ? json_decode($venue->attributes, true)
                : (array) $venue->attributes;

            if (! is_array($attrs)) {
                return;
            }

            $updates = [];
            foreach ($facilities as $facility) {
                if (! array_key_exists($facility, $attrs) || ! Schema::hasColumn('venues', $facility)) {
                    continue;
                }
                $value = $attrs[$facility];
                $updates[$facility] = is_bool($value) ? $value : match (Str::lower((string) $value)) {
                    '1', 'true', 'yes', 'y' => true,
                    '0', 'false', 'no', 'n' => false,
                    default => null,
                };
            }

            if ($updates) {
                DB::table('venues')->where('id', $venue->id)->update($updates);
            }
        });
    }

    /**
     * Old schema used status for the VA check ("To check"). New schema splits
     * that into record_status and a publishing status (draft/published).
     */
    private function splitLegacyStatus(): void
    {
        if (! Schema::hasColumn('venues', 'record_status') || ! Schema::hasColumn('venues', 'status')) {
            return;
        }

        $legacy = DB::table('venues')
            ->where(fn ($query) => $query
                ->whereNotIn('status', ['draft', 'published'])
                ->orWhereNull('status'))
            ->exists();

        if (! $legacy) {
            return;
        }

        $map = [
            'to_check' => 'to_check',
            'to check' => 'to_check',
            'checked' => 'checked',
            'needs_follow_up' => 'needs_follow_up',
            'needs follow-up' => 'needs_follow_up',
            'needs follow up' => 'needs_follow_up',
            'duplicate' => 'duplicate',
            'closed' => 'closed',
        ];

        DB::table('venues')->orderBy('id')->each(function (object $venue) use ($map) {
            $status = Str::lower(trim((string) $venue->status));

            if (in_array($status, ['draft', 'published'], true)) {
                return;
            }

            DB::table('venues')->where('id', $venue->id)->update([
                'record_status' => $map[$status] ?? 'to_check',
                'status' => 'draft',
            ]);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE venues ALTER COLUMN status SET DEFAULT 'draft'");
        }
    }
};
