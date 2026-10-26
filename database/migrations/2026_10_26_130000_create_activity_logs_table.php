<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name')->nullable();      // kept so the log still reads well after a user is deleted
            $table->string('action', 60)->index();          // e.g. role.updated, auth.login_failed
            $table->string('subject_type', 60)->nullable(); // e.g. Role, Permission, User
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();    // e.g. "Club admin", "users.view"
            $table->string('description');
            $table->boolean('succeeded')->default(true);
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
