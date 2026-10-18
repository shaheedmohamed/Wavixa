<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add CRM fields to users
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'crm_role')) {
                $table->string('crm_role')->nullable()->after('is_admin'); // 'owner', 'agent'
            }
            if (!Schema::hasColumn('users', 'crm_parent_id')) {
                $table->foreignId('crm_parent_id')->nullable()->after('crm_role')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('users', 'crm_assigned_sessions')) {
                $table->json('crm_assigned_sessions')->nullable()->after('crm_parent_id');
            }
        });

        // 2. Enhance whatsapp_conversations for CRM chat
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_conversations', 'unread_count')) {
                $table->integer('unread_count')->default(0)->after('contact_name');
            }
            if (!Schema::hasColumn('whatsapp_conversations', 'last_message')) {
                $table->text('last_message')->nullable()->after('unread_count');
            }
            if (!Schema::hasColumn('whatsapp_conversations', 'last_message_type')) {
                $table->string('last_message_type')->default('text')->after('last_message');
            }
            if (!Schema::hasColumn('whatsapp_conversations', 'last_message_direction')) {
                $table->string('last_message_direction')->default('incoming')->after('last_message_type');
            }
            if (!Schema::hasColumn('whatsapp_conversations', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('last_message_direction');
            }
        });

        // 3. Enhance whatsapp_messages for media, reactions and replies
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_messages', 'media_url')) {
                $table->text('media_url')->nullable()->after('message');
            }
            if (!Schema::hasColumn('whatsapp_messages', 'media_caption')) {
                $table->text('media_caption')->nullable()->after('media_url');
            }
            if (!Schema::hasColumn('whatsapp_messages', 'media_mime_type')) {
                $table->string('media_mime_type')->nullable()->after('media_caption');
            }
            if (!Schema::hasColumn('whatsapp_messages', 'media_size')) {
                $table->bigInteger('media_size')->nullable()->after('media_mime_type');
            }
            if (!Schema::hasColumn('whatsapp_messages', 'reactions')) {
                $table->json('reactions')->nullable()->after('media_size');
            }
            if (!Schema::hasColumn('whatsapp_messages', 'reply_to_id')) {
                $table->string('reply_to_id')->nullable()->after('reactions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['crm_role', 'crm_parent_id', 'crm_assigned_sessions']);
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropColumn(['unread_count', 'last_message', 'last_message_type', 'last_message_direction', 'is_pinned']);
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn(['media_url', 'media_caption', 'media_mime_type', 'media_size', 'reactions', 'reply_to_id']);
        });
    }
};
