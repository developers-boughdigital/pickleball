<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venue status is now just Open or Closed. The old record statuses (To check,
 * Checked, Needs follow-up, Duplicate) all count as Open.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('venues')->where('status', '!=', 'Closed')->update(['status' => 'Open']);

        Schema::table('venues', function (Blueprint $table) {
            $table->string('status', 20)->default('Open')->change();
        });
    }

    public function down(): void
    {
        DB::table('venues')->where('status', 'Open')->update(['status' => 'To check']);

        Schema::table('venues', function (Blueprint $table) {
            $table->string('status', 20)->default('To check')->change();
        });
    }
};
