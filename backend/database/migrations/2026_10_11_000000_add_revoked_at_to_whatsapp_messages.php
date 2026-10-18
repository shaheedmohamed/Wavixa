<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            // Anti-delete: the contact used "delete for everyone". The row is
            // kept intact — this stamp only marks it as revoked in the CRM UI.
            $table->timestamp('revoked_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('revoked_at');
        });
    }
};
