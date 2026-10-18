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
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('message_type')->nullable()->after('message_id');
            $table->string('status')->default('pending')->after('message_type');
            $table->foreignId('api_key_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');

            $table->index('message_type');
            $table->index('status');
            $table->index('api_key_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex(['message_type']);
            $table->dropIndex(['status']);
            $table->dropIndex(['api_key_id']);
            $table->dropColumn(['message_type', 'status', 'api_key_id', 'sent_at', 'delivered_at', 'read_at']);
        });
    }
};
