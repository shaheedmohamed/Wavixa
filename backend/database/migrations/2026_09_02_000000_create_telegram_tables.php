<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Telegram Sessions / Bots
        Schema::create('telegram_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('bot_token');
            $table->string('bot_username')->nullable();
            $table->string('bot_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('device_name')->nullable();
            $table->string('session_token')->unique();
            $table->enum('status', ['connected', 'disconnected', 'error'])->default('connected');
            $table->string('webhook_url')->nullable();
            $table->boolean('webhook_set')->default(false);
            $table->boolean('can_join_groups')->default(true);
            $table->boolean('can_read_all_group_messages')->default(false);
            $table->boolean('supports_inline_queries')->default(false);
            $table->unsignedBigInteger('messages_sent_count')->default(0);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // 2. Telegram Campaigns
        Schema::create('telegram_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('telegram_session_id')->nullable()->constrained('telegram_sessions')->onDelete('set null');
            $table->string('name');
            $table->longText('message');
            $table->string('type')->default('text'); // text, photo, document, video, audio
            $table->text('media_url')->nullable();
            $table->string('button_text')->nullable();
            $table->text('button_url')->nullable();
            $table->string('parse_mode')->default('HTML');
            $table->string('status')->default('draft'); // draft, running, paused, completed, failed, cancelled
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('delay_seconds')->default(2);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // 3. Telegram Campaign Recipients
        Schema::create('telegram_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('telegram_campaigns')->onDelete('cascade');
            $table->string('chat_id');
            $table->string('name')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });

        // 4. Telegram Messages
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('telegram_session_id')->nullable()->constrained('telegram_sessions')->onDelete('set null');
            $table->string('chat_id');
            $table->string('telegram_message_id')->nullable();
            $table->enum('direction', ['outgoing', 'incoming'])->default('outgoing');
            $table->string('type')->default('text'); // text, photo, document, video, audio, location
            $table->longText('message')->nullable();
            $table->text('media_url')->nullable();
            $table->string('parse_mode')->nullable()->default('HTML');
            $table->string('button_text')->nullable();
            $table->text('button_url')->nullable();
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained('telegram_campaigns')->onDelete('set null');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('cost')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['telegram_session_id', 'direction']);
            $table->index('scheduled_at');
        });

        // 5. Telegram Auto Replies
        Schema::create('telegram_auto_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('telegram_session_id')->constrained('telegram_sessions')->onDelete('cascade');
            $table->boolean('is_enabled')->default(false);
            $table->string('mode')->default('ai'); // 'ai' or 'rules'
            $table->text('welcome_message')->nullable();
            $table->boolean('reply_once')->default(false);
            $table->integer('delay_seconds')->default(0);
            $table->json('rules')->nullable();
            $table->longText('ai_system_prompt')->nullable();
            $table->longText('ai_business_info')->nullable();
            $table->string('ai_tone')->default('friendly');
            $table->string('ai_language')->default('ar');
            $table->string('ai_model')->nullable();
            $table->text('ai_fallback_message')->nullable();
            $table->unsignedInteger('total_replies_sent')->default(0);
            $table->timestamp('last_reply_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'telegram_session_id']);
        });

        // 6. Telegram Auto Reply Logs
        Schema::create('telegram_auto_reply_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('telegram_auto_reply_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('telegram_session_id')->constrained('telegram_sessions')->onDelete('cascade');
            $table->string('chat_id');
            $table->string('sender_name')->nullable();
            $table->text('incoming_text');
            $table->text('reply_text');
            $table->string('mode')->default('ai');
            $table->string('model')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->string('status')->default('sent');
            $table->timestamps();

            $table->index(['user_id', 'telegram_session_id']);
            $table->index('chat_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_auto_reply_logs');
        Schema::dropIfExists('telegram_auto_replies');
        Schema::dropIfExists('telegram_messages');
        Schema::dropIfExists('telegram_campaign_recipients');
        Schema::dropIfExists('telegram_campaigns');
        Schema::dropIfExists('telegram_sessions');
    }
};
