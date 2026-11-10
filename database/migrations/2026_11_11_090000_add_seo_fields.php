<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editable search titles and descriptions. Venues and clubs carry their own;
 * page_meta overrides any other page by its path (/, /play/vic, …). Empty means
 * the site writes one from the page's data.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['venues', 'clubs'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('meta_title', 70)->nullable();
                $table->string('meta_description', 170)->nullable();
            });
        }

        Schema::create('page_meta', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('title', 70)->nullable();
            $table->string('description', 170)->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_meta');

        foreach (['venues', 'clubs'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['meta_title', 'meta_description']);
            });
        }
    }
};
