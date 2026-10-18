<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_auto_reply_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('session_id')->nullable()->constrained('whatsapp_sessions')->onDelete('set null');
            $table->string('sender_phone');
            $table->string('sender_name')->nullable();
            $table->text('incoming_message')->nullable();
            $table->text('reply_message')->nullable();
            $table->string('status')->default('queued'); // queued, sent, failed, cooldown, reply_once_skipped, disabled
            $table->string('status_reason')->nullable();
            $table->unsignedBigInteger('outgoing_message_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('session_id');
            $table->index('sender_phone');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_auto_reply_logs');
    }
};
