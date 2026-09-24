<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_blogs', function (Blueprint $table) {
            $table->text('pending_wp_admin_password')->nullable();
            $table->uuid('password_reset_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_blogs', fn (Blueprint $table) => $table->dropColumn(['pending_wp_admin_password', 'password_reset_token']));
    }
};
