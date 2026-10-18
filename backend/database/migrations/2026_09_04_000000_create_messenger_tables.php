<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Connected Facebook Pages (Messenger channels)
        Schema::create('messenger_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('page_id')->index();
            $table->string('page_name')->nullable();
            $table->text('access_token'); // encrypted
            $table->string('status')->default('connected'); // connected, disconnected, error
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->boolean('webhook_set')->default(false);
            $table->unsignedBigInteger('messages_sent_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->unique(['user_id', 'page_id']);
        });

        // Messenger contacts scoped to a page (PSID)
        Schema::create('messenger_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('page_id')->constrained('messenger_pages')->onDelete('cascade');
            $table->string('psid'); // page-scoped user id
            $table->string('name')->nullable();
            $table->string('profile_pic')->nullable();
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'psid']);
            $table->index(['user_id', 'last_message_at']);
        });

        // Conversations per page/contact
        Schema::create('messenger_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('page_id')->constrained('messenger_pages')->onDelete('cascade');
            $table->foreignId('contact_id')->nullable()->constrained('messenger_contacts')->onDelete('set null');
            $table->string('psid');
            $table->timestamp('ai_disabled_until')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('messaging_window_until')->nullable();
            $table->timestamps();

            $table->unique(['page_id', 'psid']);
            $table->index(['user_id', 'last_message_at']);
        });

        // Campaigns (created before messages to satisfy foreign keys)
        Schema::create('messenger_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('page_id')->constrained('messenger_pages')->onDelete('cascade');
            $table->string('name');
            $table->longText('message');
            $table->string('status')->default('draft'); // draft, running, paused, completed, failed, cancelled
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('valid_recipients')->default(0);
            $table->unsignedInteger('invalid_recipients')->default(0);
            $table->unsignedInteger('duplicate_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('pending_count')->default(0);
            $table->unsignedInteger('excluded_count')->default(0);
            $table->unsignedInteger('delay_min')->default(2);
            $table->unsignedInteger('delay_max')->default(5);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('messenger_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('messenger_campaigns')->onDelete('cascade');
            $table->string('psid');
            $table->string('name')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed, excluded
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('external_message_id')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });

        // Messages
        Schema::create('messenger_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('page_id')->constrained('messenger_pages')->onDelete('cascade');
            $table->foreignId('conversation_id')->nullable()->constrained('messenger_conversations')->onDelete('set null');
            $table->string('psid');
            $table->enum('direction', ['incoming', 'outgoing'])->default('outgoing');
            $table->string('message_type')->default('text'); // text, image, audio, video, file, sticker
            $table->longText('message')->nullable();
            $table->text('media_url')->nullable();
            $table->string('external_message_id')->nullable();
            $table->string('status')->default('pending'); // pending, sent, delivered, failed
            $table->text('error_message')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained('messenger_campaigns')->onDelete('set null');
            $table->foreignId('campaign_recipient_id')->nullable()->constrained('messenger_campaign_recipients')->onDelete('set null');
            $table->unsignedInteger('cost')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['page_id', 'direction']);
            $table->index(['conversation_id', 'created_at']);
        });

        // Auto replies per page
        Schema::create('messenger_auto_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('page_id')->constrained('messenger_pages')->onDelete('cascade');
            $table->boolean('is_enabled')->default(false);
            $table->string('mode')->default('ai'); // ai, rules
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

            $table->unique(['page_id']);
            $table->index(['user_id', 'is_enabled']);
        });

        Schema::create('messenger_auto_reply_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('messenger_auto_reply_id')->nullable()->constrained('messenger_auto_replies')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('page_id')->constrained('messenger_pages')->onDelete('cascade');
            $table->foreignId('conversation_id')->nullable()->constrained('messenger_conversations')->onDelete('set null');
            $table->string('psid');
            $table->text('incoming_text')->nullable();
            $table->text('reply_text')->nullable();
            $table->string('mode')->default('ai');
            $table->string('model')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->string('status')->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'page_id']);
            $table->index('psid');
        });

        // Extend global contacts to support messenger/telegram identifiers
        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->string('phone')->nullable()->change();
                $table->string('channel')->default('whatsapp')->after('phone');
                $table->string('messenger_id')->nullable()->after('channel');
                $table->string('telegram_id')->nullable()->after('messenger_id');
                $table->text('notes')->nullable()->after('telegram_id');
            });

            // Rebuild unique index to allow cross-channel identifiers
            $index = 'contacts_user_id_phone_unique';
            try {
                Schema::table('contacts', function (Blueprint $table) use ($index) {
                    $table->dropUnique($index);
                });
            } catch (\Throwable $e) {
                // Index may have a different name depending on DB driver
            }

            Schema::table('contacts', function (Blueprint $table) {
                $table->unique(['user_id', 'phone', 'messenger_id', 'telegram_id'], 'contacts_user_channel_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contacts')) {
            try {
                Schema::table('contacts', function (Blueprint $table) {
                    $table->dropUnique('contacts_user_channel_unique');
                });
            } catch (\Throwable $e) {}

            Schema::table('contacts', function (Blueprint $table) {
                $table->dropColumn(['channel', 'messenger_id', 'telegram_id', 'notes']);
                $table->string('phone')->nullable(false)->change();
            });
        }

        Schema::dropIfExists('messenger_auto_reply_logs');
        Schema::dropIfExists('messenger_auto_replies');
        Schema::dropIfExists('messenger_messages');
        Schema::dropIfExists('messenger_campaign_recipients');
        Schema::dropIfExists('messenger_campaigns');
        Schema::dropIfExists('messenger_conversations');
        Schema::dropIfExists('messenger_contacts');
        Schema::dropIfExists('messenger_pages');
    }
};
