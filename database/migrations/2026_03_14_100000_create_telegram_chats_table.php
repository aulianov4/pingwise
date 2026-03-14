<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('chat_id')->unique();
            $table->string('title')->nullable();
            $table->string('type')->default('group'); // group, supergroup, channel
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_chats');
    }
};

