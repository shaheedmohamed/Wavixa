<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_auto_reply_logs', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->constrained('whatsapp_conversations')->onDelete('set null')->after('session_id');
            $table->unsignedBigInteger('incoming_message_id')->nullable()->after('incoming_message');
            $table->unsignedInteger('ai_tokens_input')->nullable()->after('reply_message');
            $table->unsignedInteger('ai_tokens_output')->nullable()->after('ai_tokens_input');
            $table->string('ai_model', 255)->nullable()->after('ai_tokens_output');
            $table->index('conversation_id');
            $table->index('incoming_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_auto_reply_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropColumn('incoming_message_id');
            $table->dropColumn('ai_tokens_input');
            $table->dropColumn('ai_tokens_output');
            $table->dropColumn('ai_model');
        });
    }
};
