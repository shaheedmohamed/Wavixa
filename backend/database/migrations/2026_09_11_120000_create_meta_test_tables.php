<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Isolated Meta Official / Tech Provider test tables.
 *
 * These tables are completely independent from the existing WhatsApp QR/Baileys
 * system and from the existing meta_whatsapp_* tables. They can be safely
 * dropped without affecting production functionality.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_test_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('business_id')->nullable()->index();
            $table->string('business_name')->nullable();
            $table->string('waba_id')->nullable()->index();
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'status']);
        });

        Schema::create('meta_test_wabas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('connection_id')->constrained('meta_test_connections')->onDelete('cascade');
            $table->string('meta_waba_id')->index();
            $table->string('name')->nullable();
            $table->string('timezone')->nullable();
            $table->string('currency')->nullable();
            $table->string('status')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'meta_waba_id']);
            $table->index('connection_id');
        });

        Schema::create('meta_test_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('waba_id')->constrained('meta_test_wabas')->onDelete('cascade');
            $table->string('meta_phone_number_id')->index();
            $table->string('phone_number');
            $table->string('display_name')->nullable();
            $table->string('quality_rating')->nullable();
            $table->string('status')->nullable();
            $table->string('messaging_limit_tier')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'meta_phone_number_id']);
            $table->index('waba_id');
        });

        Schema::create('meta_test_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('waba_id')->constrained('meta_test_wabas')->onDelete('cascade');
            $table->string('meta_template_id')->nullable()->index();
            $table->string('name');
            $table->string('category');
            $table->string('language');
            $table->string('status')->default('PENDING');
            $table->json('components')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'waba_id']);
            $table->index('status');
        });

        Schema::create('meta_test_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('phone_number_id')->constrained('meta_test_phone_numbers')->onDelete('cascade');
            $table->string('meta_message_id')->nullable()->index();
            $table->string('direction'); // incoming, outgoing
            $table->string('from_phone')->nullable();
            $table->string('to_phone')->nullable();
            $table->text('body')->nullable();
            $table->string('message_type')->nullable(); // text, template, image, etc.
            $table->json('raw_payload')->nullable();
            $table->string('status')->default('received');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'phone_number_id']);
            $table->index('created_at');
        });

        Schema::create('meta_test_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('event_type')->index();
            $table->json('payload');
            $table->string('phone_number')->nullable();
            $table->string('meta_message_id')->nullable();
            $table->string('waba_id')->nullable();
            $table->string('status')->default('received');
            $table->text('error_message')->nullable();
            $table->string('meta_signature')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['event_type', 'created_at']);
        });

        Schema::create('meta_test_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('method', 10);
            $table->string('endpoint');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('meta_error_code')->nullable();
            $table->text('meta_error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['success', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_test_api_logs');
        Schema::dropIfExists('meta_test_events');
        Schema::dropIfExists('meta_test_messages');
        Schema::dropIfExists('meta_test_templates');
        Schema::dropIfExists('meta_test_phone_numbers');
        Schema::dropIfExists('meta_test_wabas');
        Schema::dropIfExists('meta_test_connections');
    }
};
