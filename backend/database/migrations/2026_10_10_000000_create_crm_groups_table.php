<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->json('session_ids')->nullable(); // whatsapp_sessions ids assigned to this group
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'crm_group_id')) {
                $table->foreignId('crm_group_id')->nullable()->after('crm_assigned_sessions')->constrained('crm_groups')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'crm_group_id')) {
                $table->dropConstrainedForeignId('crm_group_id');
            }
        });

        Schema::dropIfExists('crm_groups');
    }
};
