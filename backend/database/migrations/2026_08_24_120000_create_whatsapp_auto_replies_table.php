<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_auto_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('session_id')->nullable()->constrained('whatsapp_sessions')->onDelete('set null');
            $table->boolean('is_enabled')->default(false);
            $table->text('reply_message')->nullable();
            $table->boolean('reply_once')->default(false);
            $table->unsignedInteger('delay_seconds')->default(0);
            $table->unsignedInteger('total_replies_sent')->default(0);
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();

            $table->index('session_id');
            $table->index('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_auto_replies');
    }
};
