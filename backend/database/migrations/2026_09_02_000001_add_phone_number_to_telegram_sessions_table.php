<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_sessions', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('user_id');
            $table->text('bot_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_sessions', function (Blueprint $table) {
            $table->dropColumn('phone_number');
        });
    }
};
