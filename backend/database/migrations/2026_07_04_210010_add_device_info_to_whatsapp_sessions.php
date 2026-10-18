<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->string('device_name', 100)->nullable()->after('phone_number');
            $table->string('browser', 100)->nullable()->after('device_name');
            $table->string('os', 100)->nullable()->after('browser');
            $table->timestamp('qr_generated_at')->nullable()->after('connected_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_sessions', function (Blueprint $table) {
            $table->dropColumn(['device_name', 'browser', 'os', 'qr_generated_at']);
        });
    }
};
