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
        Schema::create('meta_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('name')->default('Meta Business Account');
            $table->string('app_id')->nullable();
            $table->text('access_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('connected');
            $table->json('scopes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_connection_id')->constrained('meta_connections')->onDelete('cascade');
            $table->string('meta_account_id')->index(); // e.g. act_123456789
            $table->string('name');
            $table->string('currency', 10)->default('USD');
            $table->string('timezone_name')->nullable();
            $table->string('account_status')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('min_daily_budget', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('meta_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_connection_id')->constrained('meta_connections')->onDelete('cascade');
            $table->string('meta_page_id')->index();
            $table->string('name');
            $table->text('access_token')->nullable();
            $table->string('category')->nullable();
            $table->string('instagram_actor_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('meta_pixels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_connection_id')->constrained('meta_connections')->onDelete('cascade');
            $table->foreignId('meta_ad_account_id')->nullable()->constrained('meta_ad_accounts')->onDelete('set null');
            $table->string('meta_pixel_id')->index();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('meta_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ad_account_id')->constrained('meta_ad_accounts')->onDelete('cascade');
            $table->string('meta_campaign_id')->unique();
            $table->string('name');
            $table->string('objective')->default('OUTCOME_TRAFFIC');
            $table->string('status')->default('PAUSED');
            $table->string('buying_type')->default('AUCTION');
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('lifetime_budget', 12, 2)->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('stop_time')->nullable();
            $table->json('special_ad_categories')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_ad_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_campaign_id')->constrained('meta_campaigns')->onDelete('cascade');
            $table->foreignId('meta_ad_account_id')->nullable()->constrained('meta_ad_accounts')->onDelete('cascade');
            $table->string('meta_ad_set_id')->unique();
            $table->string('name');
            $table->string('status')->default('PAUSED');
            $table->decimal('daily_budget', 12, 2)->nullable();
            $table->decimal('lifetime_budget', 12, 2)->nullable();
            $table->string('optimization_goal')->nullable();
            $table->string('billing_event')->nullable();
            $table->decimal('bid_amount', 12, 2)->nullable();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->json('targeting')->nullable();
            $table->json('promoted_object')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_creatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ad_account_id')->constrained('meta_ad_accounts')->onDelete('cascade');
            $table->string('meta_creative_id')->unique();
            $table->string('name');
            $table->string('page_id')->nullable();
            $table->string('instagram_actor_id')->nullable();
            $table->text('title')->nullable();
            $table->text('body')->nullable();
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();
            $table->string('image_hash')->nullable();
            $table->string('video_id')->nullable();
            $table->string('call_to_action_type')->nullable();
            $table->text('destination_url')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ad_set_id')->constrained('meta_ad_sets')->onDelete('cascade');
            $table->foreignId('meta_creative_id')->nullable()->constrained('meta_creatives')->onDelete('set null');
            $table->foreignId('meta_ad_account_id')->nullable()->constrained('meta_ad_accounts')->onDelete('cascade');
            $table->string('meta_ad_id')->unique();
            $table->string('name');
            $table->string('status')->default('PAUSED');
            $table->string('effective_status')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });

        Schema::create('meta_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meta_ad_account_id')->nullable()->constrained('meta_ad_accounts')->onDelete('cascade');
            $table->foreignId('meta_campaign_id')->nullable()->constrained('meta_campaigns')->onDelete('cascade');
            $table->foreignId('meta_ad_set_id')->nullable()->constrained('meta_ad_sets')->onDelete('cascade');
            $table->foreignId('meta_ad_id')->nullable()->constrained('meta_ads')->onDelete('cascade');
            $table->date('date')->index();
            $table->decimal('spend', 12, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('cpc', 10, 4)->default(0);
            $table->decimal('cpm', 10, 4)->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('cpa', 10, 4)->default(0);
            $table->decimal('roas', 10, 4)->default(0);
            $table->decimal('purchase_value', 12, 2)->default(0);
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_insights');
        Schema::dropIfExists('meta_ads');
        Schema::dropIfExists('meta_creatives');
        Schema::dropIfExists('meta_ad_sets');
        Schema::dropIfExists('meta_campaigns');
        Schema::dropIfExists('meta_pixels');
        Schema::dropIfExists('meta_pages');
        Schema::dropIfExists('meta_ad_accounts');
        Schema::dropIfExists('meta_connections');
    }
};
