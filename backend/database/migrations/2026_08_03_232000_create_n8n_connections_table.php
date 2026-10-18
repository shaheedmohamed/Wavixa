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
        Schema::create('n8n_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('url', 1000);
            $table->text('api_key');
            $table->string('prefix', 32)->nullable();
            $table->string('status', 50)->default('not_connected'); // not_connected, connected, error
            $table->string('last_error', 1000)->nullable();
            $table->dateTime('last_tested_at')->nullable();
            $table->dateTime('last_used_at')->nullable();
            $table->unsignedInteger('workflows_count')->default(0);
            $table->unsignedInteger('requests_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('n8n_connections');
    }
};
