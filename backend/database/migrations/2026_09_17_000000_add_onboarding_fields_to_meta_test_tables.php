<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Embedded Signup / Coexistence metadata to the Meta Test module and
 * encrypts the stored Meta access tokens at rest.
 *
 * - meta_test_connections gains onboarding_type (cloud_api,
 *   business_app_onboarding, waba_only, obo_migration, grant_only_api_access,
 *   oauth_redirect, unknown), the raw signup event name, the session info
 *   payload reported by the JS SDK, the connection_type discriminator, and
 *   the webhook subscription timestamp.
 * - meta_test_phone_numbers gains platform_type + account_mode as reported
 *   by the Graph API (evidence for coexistence vs plain Cloud API numbers).
 * - Existing plaintext access tokens are re-encrypted in place so the new
 *   'encrypted' cast on the model can read them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_test_connections', function (Blueprint $table) {
            $table->string('connection_type')->default('meta_cloud_api')->after('waba_id');
            $table->string('onboarding_type')->default('unknown')->after('connection_type');
            $table->string('signup_event')->nullable()->after('onboarding_type');
            $table->json('session_info')->nullable()->after('signup_event');
            $table->timestamp('webhook_subscribed_at')->nullable()->after('token_expires_at');
        });

        Schema::table('meta_test_phone_numbers', function (Blueprint $table) {
            $table->string('platform_type')->nullable()->after('messaging_limit_tier');
            $table->string('account_mode')->nullable()->after('platform_type');
        });

        // Re-encrypt any plaintext tokens left over from before the encrypted cast.
        foreach (DB::table('meta_test_connections')->whereNotNull('access_token')->select('id', 'access_token')->cursor() as $row) {
            try {
                Crypt::decryptString($row->access_token);
                // Already encrypted — nothing to do.
            } catch (\Throwable) {
                try {
                    DB::table('meta_test_connections')
                        ->where('id', $row->id)
                        ->update(['access_token' => Crypt::encryptString($row->access_token)]);
                } catch (\Throwable $e) {
                    Log::warning('meta_test_connections token re-encryption failed for id ' . $row->id . ': ' . $e->getMessage());
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('meta_test_connections', function (Blueprint $table) {
            $table->dropColumn([
                'connection_type',
                'onboarding_type',
                'signup_event',
                'session_info',
                'webhook_subscribed_at',
            ]);
        });

        Schema::table('meta_test_phone_numbers', function (Blueprint $table) {
            $table->dropColumn(['platform_type', 'account_mode']);
        });
    }
};
