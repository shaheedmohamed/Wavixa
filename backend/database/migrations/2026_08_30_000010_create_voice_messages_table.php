<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voice_session_id')->constrained('voice_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('speaker', 20);
            $table->longText('text')->nullable();
            $table->string('status', 30)->default('complete');
            $table->string('provider', 50)->nullable();
            $table->string('provider_model', 100)->nullable();
            $table->unsignedInteger('tokens_input')->nullable();
            $table->unsignedInteger('tokens_output')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['voice_session_id', 'speaker']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_messages');
    }
};
