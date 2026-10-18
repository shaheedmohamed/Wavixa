<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Webhook lookups (delivered/read/media patches) hit message_id on
            // every event — without an index this is a full table scan.
            $table->index('message_id');
            // Chat polling pattern: WHERE conversation_id=? [AND id>?]
            // ORDER BY timestamp,id — covered end-to-end by this composite.
            $table->index(['conversation_id', 'timestamp', 'id'], 'wa_msgs_conv_ts_id');
            // Orphan-fallback scan used by getMessages (session + from/to).
            $table->index(['session_id', 'from'], 'wa_msgs_sess_from');
            $table->index(['session_id', 'to'], 'wa_msgs_sess_to');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['message_id']);
            $table->dropIndex('wa_msgs_conv_ts_id');
            $table->dropIndex('wa_msgs_sess_from');
            $table->dropIndex('wa_msgs_sess_to');
        });
    }
};
