<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_auto_replies', function (Blueprint $table) {
            $table->enum('mode', ['static', 'ai'])->default('static')->after('is_enabled');
            $table->text('ai_system_prompt')->nullable()->after('mode');
            $table->text('ai_business_info')->nullable()->after('ai_system_prompt');
            $table->string('ai_tone', 50)->nullable()->after('ai_business_info');
            $table->string('ai_language', 50)->nullable()->after('ai_tone');
            $table->unsignedSmallInteger('ai_context_limit')->default(10)->after('ai_language');
            $table->boolean('ai_fallback_enabled')->default(false)->after('ai_context_limit');
            $table->text('ai_fallback_message')->nullable()->after('ai_fallback_enabled');
            $table->unsignedSmallInteger('ai_max_daily_replies')->default(0)->after('ai_fallback_message');
            $table->unsignedInteger('ai_total_replies_sent')->default(0)->after('ai_max_daily_replies');
            $table->unsignedInteger('ai_daily_replies_sent')->default(0)->after('ai_total_replies_sent');
            $table->date('ai_replies_today_date')->nullable()->after('ai_daily_replies_sent');
            $table->timestamp('ai_last_reply_at')->nullable()->after('ai_replies_today_date');
            $table->boolean('ai_business_hours_enabled')->default(false)->after('ai_last_reply_at');
            $table->time('ai_business_hours_from')->nullable()->after('ai_business_hours_enabled');
            $table->time('ai_business_hours_to')->nullable()->after('ai_business_hours_from');
            $table->string('ai_model', 255)->nullable()->after('ai_business_hours_to');
            $table->unsignedSmallInteger('ai_handoff_minutes')->default(0)->after('ai_model');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_auto_replies', function (Blueprint $table) {
            $table->dropColumn([
                'mode',
                'ai_system_prompt',
                'ai_business_info',
                'ai_tone',
                'ai_language',
                'ai_context_limit',
                'ai_fallback_enabled',
                'ai_fallback_message',
                'ai_max_daily_replies',
                'ai_total_replies_sent',
                'ai_daily_replies_sent',
                'ai_replies_today_date',
                'ai_last_reply_at',
                'ai_business_hours_enabled',
                'ai_business_hours_from',
                'ai_business_hours_to',
                'ai_model',
                'ai_handoff_minutes',
            ]);
        });
    }
};
