<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk campaign tables for the Meta Official WhatsApp module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_test_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('connection_id')->constrained('meta_test_connections')->onDelete('cascade');
            $table->foreignId('waba_id')->constrained('meta_test_wabas')->onDelete('cascade');
            $table->foreignId('phone_number_id')->constrained('meta_test_phone_numbers')->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->constrained('meta_test_templates')->onDelete('set null');
            $table->string('name');
            $table->string('message_type')->default('marketing'); // marketing, utility, authentication, text
            $table->text('text')->nullable(); // free-form body for 24h-window sends
            $table->string('status')->default('draft'); // draft, running, paused, completed, failed
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('valid_recipients')->default(0);
            $table->unsignedInteger('invalid_recipients')->default(0);
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedSmallInteger('delay_seconds')->default(2);
            $table->boolean('opt_in_confirmed')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('meta_test_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('meta_test_campaigns')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('phone');
            $table->boolean('is_valid')->default(true);
            $table->string('status')->default('pending'); // pending, sent, delivered, read, failed
            $table->string('meta_message_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable(); // meta_test_messages row
            $table->text('error_message')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('variables')->nullable(); // template body parameters
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('phone');
            $table->index('meta_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_test_campaign_recipients');
        Schema::dropIfExists('meta_test_campaigns');
    }
};
