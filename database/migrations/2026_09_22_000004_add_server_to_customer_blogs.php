<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_blogs', function (Blueprint $table) {
            $table->foreignId('server_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_blogs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('server_id');
        });
    }
};
