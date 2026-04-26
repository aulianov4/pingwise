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
        Schema::table('audit_pages', function (Blueprint $table): void {
            $table->smallInteger('crawl_depth')->nullable()->after('in_crawl');
            $table->timestamp('removed_from_sitemap_at')->nullable()->after('last_in_sitemap_at');
            $table->index(['site_id', 'crawl_depth']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_pages', function (Blueprint $table): void {
            $table->dropIndex(['site_id', 'crawl_depth']);
            $table->dropColumn(['crawl_depth', 'removed_from_sitemap_at']);
        });
    }
};
