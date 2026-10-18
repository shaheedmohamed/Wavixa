<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('real_time_events', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // login, logout, device_connected, device_disconnected, message_sent, message_failed, api_key_created, subscription_purchased, subscription_expired, session_expired
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('real_time_events');
    }
};
