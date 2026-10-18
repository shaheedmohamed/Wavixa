<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            // The agent currently handling this chat. Set on their first
            // reply; other agents are blocked from sending until resolved.
            $table->unsignedBigInteger('assigned_agent_id')->nullable()->index()->after('is_pinned');
            $table->timestamp('assigned_at')->nullable()->after('assigned_agent_id');
            // Resolution state + optional customer rating captured at close.
            $table->timestamp('resolved_at')->nullable()->index()->after('assigned_at');
            $table->unsignedBigInteger('resolved_by')->nullable()->after('resolved_at');
            $table->unsignedTinyInteger('rating')->nullable()->after('resolved_by');
            $table->text('rating_note')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropColumn([
                'assigned_agent_id',
                'assigned_at',
                'resolved_at',
                'resolved_by',
                'rating',
                'rating_note',
            ]);
        });
    }
};
