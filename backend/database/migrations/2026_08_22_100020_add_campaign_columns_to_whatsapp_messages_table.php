<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('api_key_id')->constrained('whatsapp_campaigns')->onDelete('set null');
            $table->foreignId('campaign_recipient_id')->nullable()->after('campaign_id')->constrained('whatsapp_campaign_recipients')->onDelete('set null');

            $table->index('campaign_id');
            $table->index('campaign_recipient_id');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropForeign(['campaign_recipient_id']);
            $table->dropColumn(['campaign_id', 'campaign_recipient_id']);
        });
    }
};
