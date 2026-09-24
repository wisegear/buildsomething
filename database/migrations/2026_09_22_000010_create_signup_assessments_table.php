<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signup_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('registration_ip', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->timestampTz('registered_at');
            $table->text('accept_language')->nullable();
            $table->text('referrer')->nullable();
            $table->unsignedBigInteger('previous_ip_registrations')->default(0);
            $table->string('email_domain')->nullable()->index();
            $table->char('country_code', 2)->nullable()->index();
            foreach (['country_name', 'region', 'city', 'network', 'isp', 'organisation', 'connection_type', 'ip_intelligence_provider'] as $field) {
                $table->string($field)->nullable();
            }
            $table->bigInteger('asn')->nullable();
            foreach (['vpn_detected', 'proxy_detected', 'tor_detected', 'hosting_detected', 'disposable_email_detected'] as $field) {
                $table->boolean($field)->nullable();
            }
            $table->decimal('risk_score', 12, 4)->nullable();
            $table->timestampTz('ip_intelligence_checked_at')->nullable();
            $table->string('status', 20)->default('clear')->index();
            $table->jsonb('risk_flags')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_assessments');
    }
};
