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
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('job_id')->nullable()->after('message_id')->index();
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->text('error_message')->nullable()->after('attempts');
            $table->timestamp('queued_at')->nullable()->after('error_message');
            $table->timestamp('processed_at')->nullable()->after('queued_at');
            $table->timestamp('completed_at')->nullable()->after('processed_at');
            $table->timestamp('failed_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn([
                'job_id',
                'attempts',
                'error_message',
                'queued_at',
                'processed_at',
                'completed_at',
                'failed_at',
            ]);
        });
    }
};
