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
        Schema::create('audit_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->smallInteger('status_code')->default(0);
            $table->boolean('in_sitemap')->default(false);
            $table->boolean('in_crawl')->default(false);
            $table->string('redirect_target')->nullable();
            $table->string('canonical')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('last_in_sitemap_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'url']);
            $table->index(['site_id', 'in_sitemap']);
            $table->index(['site_id', 'in_crawl']);
            $table->index(['site_id', 'status_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_pages');
    }
};
