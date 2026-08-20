<?php

use App\Models\SiteTest;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        SiteTest::query()
            ->where('test_type', 'sitemap')
            ->update(['is_enabled' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        SiteTest::query()
            ->where('test_type', 'sitemap')
            ->update(['is_enabled' => true]);
    }
};
