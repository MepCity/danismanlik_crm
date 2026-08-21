<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->boolean('consent_call')->nullable();
            $table->boolean('do_not_call')->default(false)->index();
        });

        DB::statement(<<<'SQL'
            WITH latest_call_consent AS (
                SELECT DISTINCT ON (contact_id)
                    contact_id,
                    status
                FROM communication_consents
                WHERE channel = 'call'
                  AND purpose = 'marketing'
                  AND effective_from <= CURRENT_TIMESTAMP
                ORDER BY contact_id, effective_from DESC, id DESC
            )
            UPDATE contacts
            SET consent_call = latest_call_consent.status = 'granted',
                do_not_call = latest_call_consent.status IN ('denied', 'withdrawn')
            FROM latest_call_consent
            WHERE contacts.id = latest_call_consent.contact_id
            SQL);

        DB::statement("COMMENT ON COLUMN contacts.consent_call IS 'Yalnızca hızlı sorgu için güncel özet; gerçek kaynak communication_consents tablosudur'");
        DB::statement("COMMENT ON COLUMN contacts.do_not_call IS 'Yalnızca hızlı sorgu için güncel özet; gerçek kaynak communication_consents tablosudur'");
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropColumn(['consent_call', 'do_not_call']);
        });
    }
};
