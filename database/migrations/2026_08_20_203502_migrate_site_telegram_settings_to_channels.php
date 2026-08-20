<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $sites = DB::table('sites')
            ->whereNotNull('telegram_chat_id')
            ->whereNotNull('project_id')
            ->get();

        foreach ($sites as $site) {
            $chat = DB::table('telegram_chats')->where('id', $site->telegram_chat_id)->first();

            if ($chat === null) {
                continue;
            }

            $channelId = DB::table('notification_channels')
                ->where('project_id', $site->project_id)
                ->where('telegram_chat_id', $chat->chat_id)
                ->value('id');

            if ($channelId === null) {
                $channelId = DB::table('notification_channels')->insertGetId([
                    'project_id' => $site->project_id,
                    'name' => $chat->title ?: 'Telegram',
                    'type' => 'telegram',
                    'is_enabled' => true,
                    'telegram_chat_id' => $chat->chat_id,
                    'config' => json_encode([
                        'chat_title' => $chat->title,
                        'chat_type' => $chat->type,
                        'connected_at' => now()->toIso8601String(),
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $settings = is_string($site->notification_settings)
                ? json_decode($site->notification_settings, true)
                : ($site->notification_settings ?? []);
            $settings = is_array($settings) ? $settings : [];

            $alerts = (bool) ($settings['alerts_enabled'] ?? false);
            $dailySummary = (bool) ($settings['summary_enabled'] ?? false);

            if (! $alerts && ! $dailySummary) {
                continue;
            }

            $siteTests = DB::table('site_tests')->where('site_id', $site->id)->get();

            foreach ($siteTests as $siteTest) {
                $exists = DB::table('site_test_notification_channel')
                    ->where('site_test_id', $siteTest->id)
                    ->where('notification_channel_id', $channelId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('site_test_notification_channel')->insert([
                    'site_test_id' => $siteTest->id,
                    'notification_channel_id' => $channelId,
                    'alerts' => $alerts,
                    'daily_summary' => $dailySummary,
                    'weekly_summary' => false,
                    'monthly_summary' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('sites', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('telegram_chat_id');
            $table->dropColumn('notification_settings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table): void {
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
};
