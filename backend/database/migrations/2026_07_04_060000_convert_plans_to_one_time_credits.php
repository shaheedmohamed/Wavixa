<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert all plans to one-time message credit packages
        DB::table('plans')->update(['interval' => 'one_time']);

        // Convert existing subscriptions to non-expiring credits
        DB::table('subscriptions')
            ->where('status', 'active')
            ->update([
                'ends_at' => null,
                'renews_at' => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not reversible in a meaningful way
    }
};
