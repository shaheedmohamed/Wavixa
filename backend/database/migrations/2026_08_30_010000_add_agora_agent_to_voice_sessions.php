<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voice_sessions', function (Blueprint $table) {
            $table->string('agora_agent_uid')->nullable()->after('agora_uid');
            $table->string('agora_agent_id')->nullable()->after('agora_agent_uid');
        });
    }

    public function down(): void
    {
        Schema::table('voice_sessions', function (Blueprint $table) {
            $table->dropColumn(['agora_agent_uid', 'agora_agent_id']);
        });
    }
};
