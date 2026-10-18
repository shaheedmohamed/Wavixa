<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for the independent Wavixa WhatsApp Engine tables.
     */
    public function up(): void
    {
        // 1. WhatsApp Devices
        if (!Schema::hasTable('whatsapp_devices')) {
            Schema::create('whatsapp_devices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->string('session_id', 64)->unique();
                $table->string('phone_number', 32)->nullable();
                $table->string('push_name', 128)->nullable();
                $table->string('platform', 64)->default('wavixa-engine');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('user_id');
            });
        }

        // 2. WhatsApp Encrypted Credentials & Key Vault
        if (!Schema::hasTable('whatsapp_credentials')) {
            Schema::create('whatsapp_credentials', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 64)->unique();
                $table->unsignedInteger('registration_id');
                $table->binary('identity_key_public');
                $table->mediumText('encrypted_key_bundle'); // base64 encoded AES-256-GCM ciphertext
                $table->string('key_bundle_iv', 64);
                $table->string('key_bundle_tag', 64);
                $table->unsignedInteger('version')->default(1);
                $table->timestamps();
            });
        }

        // 3. WhatsApp Message Statuses
        if (!Schema::hasTable('whatsapp_message_statuses')) {
            Schema::create('whatsapp_message_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 64);
                $table->string('message_id', 128);
                $table->enum('status', ['queued', 'sending', 'sent', 'delivered', 'read', 'failed']);
                $table->string('error_code', 64)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('timestamp')->useCurrent();

                $table->index(['session_id', 'message_id']);
            });
        }

        // 4. WhatsApp Health Monitoring
        if (!Schema::hasTable('whatsapp_health')) {
            Schema::create('whatsapp_health', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 64)->unique();
                $table->string('worker_id', 64);
                $table->enum('status', ['HEALTHY', 'DEGRADED', 'UNHEALTHY', 'OFFLINE'])->default('HEALTHY');
                $table->unsignedInteger('ping_latency_ms')->default(0);
                $table->unsignedInteger('queue_size')->default(0);
                $table->unsignedInteger('error_count')->default(0);
                $table->unsignedInteger('reconnect_count')->default(0);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        // 5. WhatsApp Audit Logs
        if (!Schema::hasTable('whatsapp_audit_logs')) {
            Schema::create('whatsapp_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('session_id', 64);
                $table->string('event', 64);
                $table->json('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['session_id', 'event']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_audit_logs');
        Schema::dropIfExists('whatsapp_health');
        Schema::dropIfExists('whatsapp_message_statuses');
        Schema::dropIfExists('whatsapp_credentials');
        Schema::dropIfExists('whatsapp_devices');
    }
};
