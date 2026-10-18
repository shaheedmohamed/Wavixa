<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the database structure for Meta WhatsApp Business Platform
     * integration. This is isolated from the existing Wavixa WhatsApp/Baileys
     * tables and does not modify them.
     */
    public function up(): void
    {
        Schema::create('meta_whatsapp_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('waba_id')->nullable(); // WhatsApp Business Account ID from Meta
            $table->string('business_portfolio_id')->nullable();
            $table->string('business_portfolio_name')->nullable();
            $table->text('access_token')->nullable(); // encrypted by the model
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status')->default('pending'); // pending, incomplete, connected, restricted, stopped
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('waba_id');
        });

        Schema::create('meta_whatsapp_wabas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('connection_id')->constrained('meta_whatsapp_connections')->onDelete('cascade');
            $table->string('meta_waba_id')->index(); // e.g. 123456789
            $table->string('name')->nullable();
            $table->string('timezone')->nullable();
            $table->string('currency')->nullable();
            $table->string('status')->nullable(); // ACTIVE, PENDING, RESTRICTED, etc.
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'meta_waba_id']);
            $table->index('connection_id');
        });

        Schema::create('meta_whatsapp_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('waba_id')->constrained('meta_whatsapp_wabas')->onDelete('cascade');
            $table->string('meta_phone_number_id')->index(); // e.g. 123456789
            $table->string('phone_number');
            $table->string('display_name')->nullable();
            $table->string('quality_rating')->nullable();
            $table->string('status')->nullable(); // CONNECTED, PENDING, etc.
            $table->boolean('is_active')->default(true);
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'meta_phone_number_id']);
            $table->index('waba_id');
        });

        Schema::create('meta_whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('waba_id')->constrained('meta_whatsapp_wabas')->onDelete('cascade');
            $table->string('meta_template_id')->nullable()->index(); // template id from Meta
            $table->string('name');
            $table->string('category'); // MARKETING, UTILITY, AUTHENTICATION
            $table->string('language');
            $table->string('status')->default('PENDING'); // APPROVED, PENDING, REJECTED, PAUSED
            $table->json('components')->nullable(); // header, body, footer, buttons
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'waba_id']);
            $table->index('status');
        });

        Schema::create('meta_whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('connection_id')->constrained('meta_whatsapp_connections')->onDelete('cascade');
            $table->foreignId('waba_id')->constrained('meta_whatsapp_wabas')->onDelete('cascade');
            $table->foreignId('phone_number_id')->constrained('meta_whatsapp_phone_numbers')->onDelete('cascade');
            $table->foreignId('template_id')->constrained('meta_whatsapp_templates')->onDelete('cascade');
            $table->string('name');
            $table->string('message_type')->default('marketing'); // marketing, utility, authentication
            $table->string('status')->default('draft'); // draft, pending, running, paused, completed, failed
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('valid_recipients')->default(0);
            $table->unsignedInteger('invalid_recipients')->default(0);
            $table->unsignedInteger('queued_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('read_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->boolean('opt_in_confirmed')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });

        Schema::create('meta_whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('meta_whatsapp_campaigns')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('phone');
            $table->boolean('is_valid')->default(true);
            $table->string('status')->default('pending'); // pending, queued, sent, delivered, read, failed
            $table->string('meta_message_id')->nullable(); // wamid from Meta
            $table->text('error_message')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('variables')->nullable();
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('phone');
            $table->index('status');
            $table->index('meta_message_id');
        });

        Schema::create('meta_whatsapp_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('event_type');
            $table->json('payload');
            $table->string('status')->default('received'); // received, processed, failed
            $table->text('error_message')->nullable();
            $table->string('meta_signature')->nullable();
            $table->timestamps();

            $table->index('event_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_whatsapp_webhook_logs');
        Schema::dropIfExists('meta_whatsapp_campaign_recipients');
        Schema::dropIfExists('meta_whatsapp_campaigns');
        Schema::dropIfExists('meta_whatsapp_templates');
        Schema::dropIfExists('meta_whatsapp_phone_numbers');
        Schema::dropIfExists('meta_whatsapp_wabas');
        Schema::dropIfExists('meta_whatsapp_connections');
    }
};
