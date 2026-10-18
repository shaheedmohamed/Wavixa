<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_test_messages', function (Blueprint $table) {
            $table->unsignedBigInteger('api_key_id')->nullable()->after('user_id')->index();
            $table->string('conversation_id')->nullable()->index();
            $table->string('pricing_category')->nullable();   // service | utility | marketing | authentication
            $table->string('pricing_type')->nullable();       // e.g. free_customer_service, regular
            $table->string('pricing_model')->nullable();      // PMP / CBP
            $table->boolean('pricing_billable')->nullable();  // Meta bills this message or not
        });

        Schema::table('meta_test_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('api_key_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('meta_test_messages', function (Blueprint $table) {
            $table->dropIndex(['api_key_id']);
            $table->dropIndex(['conversation_id']);
            $table->dropColumn(['api_key_id', 'conversation_id', 'pricing_category', 'pricing_type', 'pricing_model', 'pricing_billable']);
        });

        Schema::table('meta_test_campaigns', function (Blueprint $table) {
            $table->dropIndex(['api_key_id']);
            $table->dropColumn('api_key_id');
        });
    }
};
