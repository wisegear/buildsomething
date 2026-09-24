<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('seo_summary');
            $table->text('body');
            $table->string('image')->nullable();
            $table->string('image_alt')->nullable();
            $table->boolean('is_published')->default(false);
            $table->date('post_date');
            $table->timestamps();
            $table->index(['is_published', 'post_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
