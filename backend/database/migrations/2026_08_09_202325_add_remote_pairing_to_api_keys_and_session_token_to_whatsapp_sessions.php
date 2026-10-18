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
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->string('session_token', 64)->nullable()->unique()->after('user_id');
            $table->foreignId('api_key_id')->nullable()->after('user_id')->constrained('api_keys')->onDelete('set null');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->boolean('allow_remote_pairing')->default(false)->after('whatsapp_session_id');
            $table->unsignedInteger('remote_pairing_limit')->default(1)->after('allow_remote_pairing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['allow_remote_pairing', 'remote_pairing_limit']);
        });

        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropForeign(['api_key_id']);
            $table->dropColumn(['session_token', 'api_key_id', 'qr_generated_at']);
        });
    }
};
