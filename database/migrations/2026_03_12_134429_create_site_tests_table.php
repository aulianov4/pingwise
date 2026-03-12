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
        Schema::create('site_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('test_type'); // 'availability', 'ssl', 'domain'
            $table->boolean('is_enabled')->default(true);
            $table->json('settings')->nullable(); // Настройки интервала и другие параметры
            $table->timestamps();
            
            $table->unique(['site_id', 'test_type']);
            $table->index(['site_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_tests');
    }
};
