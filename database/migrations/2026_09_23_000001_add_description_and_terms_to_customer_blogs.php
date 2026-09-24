<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_blogs', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->boolean('terms_accepted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('customer_blogs', function (Blueprint $table) {
            $table->dropColumn(['description', 'terms_accepted']);
        });
    }
};
