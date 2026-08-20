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
        Schema::create('server_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('status');
            $table->json('value')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->index(['server_id', 'reported_at']);
            $table->index('reported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_heartbeats');
    }
};
