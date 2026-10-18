<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            $table->unsignedInteger('duration_ms')->nullable()->after('status_code');
            $table->unsignedInteger('request_size')->nullable()->after('duration_ms');
            $table->unsignedInteger('response_size')->nullable()->after('request_size');
            $table->string('country', 100)->nullable()->after('response_size');
            $table->string('city', 100)->nullable()->after('country');
            $table->index('duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            $table->dropColumn(['duration_ms', 'request_size', 'response_size', 'country', 'city']);
        });
    }
};
