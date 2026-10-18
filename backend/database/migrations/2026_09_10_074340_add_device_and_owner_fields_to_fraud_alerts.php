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
        Schema::table('fraud_alerts', function (Blueprint $table) {
            $table->string('user_agent')->nullable()->after('ip_address');
            $table->string('device_type', 50)->nullable()->after('user_agent');
            $table->string('device_name', 255)->nullable()->after('device_type');
            $table->string('device_fingerprint', 64)->nullable()->after('device_name');
            $table->string('owner_name', 255)->nullable()->after('device_fingerprint');
            $table->string('owner_email', 255)->nullable()->after('owner_name');
            $table->string('owner_phone', 50)->nullable()->after('owner_email');
            $table->index(['device_fingerprint', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['type', 'severity', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fraud_alerts', function (Blueprint $table) {
            $table->dropColumn(['user_agent', 'device_type', 'device_name', 'device_fingerprint', 'owner_name', 'owner_email', 'owner_phone']);
            $table->dropIndex(['device_fingerprint_created_at']); // may not match naming
            $table->dropIndex(['ip_address_created_at']);
            $table->dropIndex(['type_severity_created_at']);
        });
    }
};
