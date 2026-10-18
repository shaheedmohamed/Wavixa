<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Raw WhatsApp CDN media coordinates (mediaKey, directPath, mimetype…)
            // for history-synced media that gets decrypted lazily via the engine.
            $table->json('media_meta')->nullable()->after('media_url');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('media_meta');
        });
    }
};
