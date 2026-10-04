<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_blogs', function (Blueprint $table) {
            $table->unsignedInteger('workers')->nullable();
            $table->unsignedInteger('memory_mb')->nullable();
            $table->unsignedInteger('pending_workers')->nullable();
            $table->unsignedInteger('pending_memory_mb')->nullable();
            $table->uuid('resource_update_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_blogs', fn (Blueprint $table) => $table->dropColumn(['workers', 'memory_mb', 'pending_workers', 'pending_memory_mb', 'resource_update_token']));
    }
};
