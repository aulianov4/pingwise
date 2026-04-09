<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('sites')
            ->whereNull('notification_settings')
            ->update([
                'notification_settings' => json_encode([
                    'alerts_enabled' => false,
                    'summary_enabled' => false,
                ]),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Не откатываем — данные уже могут быть изменены пользователем
    }
};
