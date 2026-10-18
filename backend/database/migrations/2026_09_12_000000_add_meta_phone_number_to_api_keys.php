<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->unsignedBigInteger('meta_phone_number_id')->nullable()->after('whatsapp_session_id')->index();
            $table->foreign('meta_phone_number_id')
                ->references('id')
                ->on('meta_test_phone_numbers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropForeign(['meta_phone_number_id']);
            $table->dropColumn('meta_phone_number_id');
        });
    }
};
