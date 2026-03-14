<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->foreignId('telegram_chat_id')
                ->nullable()
                ->after('is_active')
                ->constrained('telegram_chats')
                ->nullOnDelete();
            $table->json('notification_settings')
                ->nullable()
                ->after('telegram_chat_id');
        });
    }
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('telegram_chat_id');
            $table->dropColumn('notification_settings');
        });
    }
};
