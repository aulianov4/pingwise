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
        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('test_type'); // 'availability', 'ssl', 'domain'
            $table->string('status'); // 'success', 'failed', 'warning'
            $table->json('value')->nullable(); // Детальные данные результата
            $table->text('message')->nullable(); // Детальное описание результата
            $table->timestamp('checked_at');
            $table->timestamps();
            
            $table->index(['site_id', 'test_type', 'checked_at']);
            $table->index(['site_id', 'checked_at']);
            $table->index('checked_at'); // Для очистки старых данных
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};
