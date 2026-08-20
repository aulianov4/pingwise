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
        Schema::create('notification_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->boolean('is_enabled')->default(true);
            $table->bigInteger('telegram_chat_id')->nullable();
            $table->string('connect_token')->nullable();
            $table->timestamp('connect_token_expires_at')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'telegram_chat_id']);
            $table->unique('connect_token');
            $table->index(['type', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_channels');
    }
};
