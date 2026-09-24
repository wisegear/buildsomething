<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signup_assessments', function (Blueprint $table) {
            $table->dropIndex(['country_code']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'country_code', 'country_name', 'region', 'city', 'asn',
                'network', 'isp', 'organisation', 'connection_type',
                'risk_score', 'ip_intelligence_provider', 'ip_intelligence_checked_at',
                'vpn_detected', 'proxy_detected', 'tor_detected', 'hosting_detected',
                'disposable_email_detected', 'risk_flags', 'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('signup_assessments', function (Blueprint $table) {
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
        });
    }
};
