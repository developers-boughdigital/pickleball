<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The article studio: articles with categories, nested tags and a history of
 * saved versions, plus the editorial calendar that plans what gets written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('article_tags', function (Blueprint $table) {
            $table->id();
            // A tag can sit under another: Technique › Serving › Spin serve.
            $table->foreignId('parent_id')->nullable()->constrained('article_tags')->nullOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('featured_image')->nullable();
            // draft, scheduled or published. A scheduled article is live once published_at has passed.
            $table->string('status', 20)->default('draft')->index();
            $table->dateTime('published_at')->nullable()->index();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('article_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            // What Claude was asked for: topic, source URLs, instructions and length.
            $table->json('brief')->nullable();
            // null (written by hand), pending, writing, done or failed.
            $table->string('writing_status', 20)->nullable();
            $table->dateTime('writing_started_at')->nullable();
            $table->string('writing_error', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('article_tag', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'article_tag_id']);
        });

        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 60);
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->dateTime('created_at');
        });

        Schema::create('editorial_plans', function (Blueprint $table) {
            $table->id();
            $table->date('planned_for')->index();
            $table->string('topic');
            $table->foreignId('article_category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_plans');
        Schema::dropIfExists('article_revisions');
        Schema::dropIfExists('article_tag');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('article_tags');
        Schema::dropIfExists('article_categories');
    }
};
