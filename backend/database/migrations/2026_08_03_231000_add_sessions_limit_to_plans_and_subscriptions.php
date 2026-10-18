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
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('sessions_limit')->default(1)->after('messages_limit');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('sessions_limit')->default(1)->after('messages_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('sessions_limit');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('sessions_limit');
        });
    }
};
