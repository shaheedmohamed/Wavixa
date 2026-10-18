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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('reputation_score')->default(0)->after('status');
            $table->string('risk_level')->default('Low Risk')->after('reputation_score');
            $table->json('reputation_factors')->nullable()->after('risk_level');
            $table->timestamp('reputation_calculated_at')->nullable()->after('reputation_factors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['reputation_score', 'risk_level', 'reputation_factors', 'reputation_calculated_at']);
        });
    }
};
