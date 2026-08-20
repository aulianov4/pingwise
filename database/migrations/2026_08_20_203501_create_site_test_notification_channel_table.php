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
        Schema::create('site_test_notification_channel', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_channel_id')->constrained()->cascadeOnDelete();
            $table->boolean('alerts')->default(false);
            $table->boolean('daily_summary')->default(false);
            $table->boolean('weekly_summary')->default(false);
            $table->boolean('monthly_summary')->default(false);
            $table->timestamps();

            $table->unique(['site_test_id', 'notification_channel_id'], 'site_test_channel_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_test_notification_channel');
    }
};
