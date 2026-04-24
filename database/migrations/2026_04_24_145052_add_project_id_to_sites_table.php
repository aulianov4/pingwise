<?php

use App\Models\Project;
use App\Models\Site;
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
        Schema::table('sites', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // Переносим все существующие сайты в проект "По умолчанию"
        if (Site::query()->exists()) {
            $defaultProject = Project::query()->create([
                'name' => 'По умолчанию',
                'description' => 'Проект создан автоматически при миграции',
            ]);

            Site::query()->whereNull('project_id')->update(['project_id' => $defaultProject->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
