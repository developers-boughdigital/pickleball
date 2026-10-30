<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Venues use UUIDs, so the subject of a log entry can't be an integer any more.
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table activity_logs alter column subject_id type varchar(36) using subject_id::varchar');

            return;
        }

        Schema::table('activity_logs', fn (Blueprint $table) => $table->string('subject_id', 36)->nullable()->change());
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Only numeric ids can go back to a bigint.
            DB::statement("update activity_logs set subject_id = null where subject_id !~ '^[0-9]+$'");
            DB::statement('alter table activity_logs alter column subject_id type bigint using subject_id::bigint');

            return;
        }

        Schema::table('activity_logs', fn (Blueprint $table) => $table->unsignedBigInteger('subject_id')->nullable()->change());
    }
};
