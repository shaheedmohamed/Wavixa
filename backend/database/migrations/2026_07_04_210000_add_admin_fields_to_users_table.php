<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('active')->index()->after('is_admin'); // active, suspended, banned
            $table->timestamp('banned_at')->nullable()->after('status');
            $table->integer('messages_balance')->default(0)->after('banned_at');
            $table->string('country', 100)->nullable()->after('messages_balance');
            $table->string('city', 100)->nullable()->after('country');
            $table->string('last_ip', 45)->nullable()->after('city');
            $table->string('last_device', 100)->nullable()->after('last_ip');
            $table->string('last_browser', 100)->nullable()->after('last_device');
            $table->string('last_os', 100)->nullable()->after('last_browser');
            $table->timestamp('last_activity_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'banned_at', 'messages_balance', 'country', 'city', 'last_ip', 'last_device', 'last_browser', 'last_os', 'last_activity_at']);
        });
    }
};
