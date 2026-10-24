<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_test_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('reply_to_id')->nullable()->after('meta_message_id')->index();
            $table->boolean('is_hidden')->default(false)->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('meta_test_messages', function (Blueprint $table) {
            $table->dropColumn(['reply_to_id', 'is_hidden']);
        });
    }
};
