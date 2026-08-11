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
        Schema::table('contacts', static function (Blueprint $table): void {
            $table->string('data_source')->nullable();
        });
        DB::statement("UPDATE contacts SET data_source = COALESCE(NULLIF(btrim(companies.source), ''), 'other') FROM companies WHERE contacts.company_id = companies.id");
        DB::statement('ALTER TABLE contacts ALTER COLUMN data_source SET NOT NULL');
        DB::statement('ALTER TABLE contacts ADD CONSTRAINT contacts_data_source_not_blank CHECK (length(btrim(data_source)) > 0)');

        Schema::table('statuses', static function (Blueprint $table): void {
            $table->jsonb('required_fields')->default('[]');
            $table->boolean('is_initial')->default(false)->index();
            $table->boolean('converts_to_deal')->default(false);
        });

        DB::table('statuses')->where('type', 'lead')->where('code', 'new')->update(['is_initial' => true]);
        DB::table('statuses')->where('type', 'deal')->where('code', 'awaiting_assignment')->update(['is_initial' => true]);
        DB::table('statuses')->where('type', 'lead')->where('code', 'callback')->update([
            'required_fields' => json_encode(['next_call_at', 'owner_user_id'], JSON_THROW_ON_ERROR),
        ]);
        DB::table('statuses')->where('type', 'lead')->where('code', 'lost')->update([
            'required_fields' => json_encode(['lost_reason'], JSON_THROW_ON_ERROR),
        ]);
        DB::table('statuses')->where('type', 'lead')->where('code', 'won')->update([
            'required_fields' => json_encode(['program_version_id'], JSON_THROW_ON_ERROR),
            'converts_to_deal' => true,
        ]);

        Schema::table('leads', static function (Blueprint $table): void {
            $table->foreignId('converted_deal_id')->nullable()->unique()->constrained('deals')->restrictOnDelete();
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION enforce_lead_status_requirements()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                required_fields jsonb;
            BEGIN
                SELECT statuses.required_fields INTO required_fields
                FROM statuses
                WHERE statuses.id = NEW.status_id AND statuses.type = 'lead';

                IF required_fields ? 'next_call_at' AND NEW.next_call_at IS NULL THEN
                    RAISE EXCEPTION 'next_call_at is required for target status';
                END IF;

                IF required_fields ? 'owner_user_id' AND NEW.owner_user_id IS NULL THEN
                    RAISE EXCEPTION 'owner_user_id is required for target status';
                END IF;

                IF required_fields ? 'lost_reason' AND (NEW.lost_reason IS NULL OR length(btrim(NEW.lost_reason)) = 0) THEN
                    RAISE EXCEPTION 'lost_reason is required for target status';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER leads_status_requirements
            BEFORE INSERT OR UPDATE ON leads
            FOR EACH ROW
            EXECUTE FUNCTION enforce_lead_status_requirements();
            SQL);

        DB::statement("COMMENT ON COLUMN contacts.data_source IS 'KVKK aydınlatması için iletişim bilgisinin zorunlu edinim kaynağı'");
        DB::statement("COMMENT ON COLUMN statuses.required_fields IS 'Statüye geçiş formunda ve DB tetikleyicisinde zorunlu tutulan alan anahtarları; davranış koda gömülmez'");
        DB::statement("COMMENT ON COLUMN statuses.is_initial IS 'Yeni öznenin başladığı anlamsal statü; tip başına en fazla bir etkin satır beklenir'");
        DB::statement("COMMENT ON COLUMN statuses.converts_to_deal IS 'Fırsat geçişinin atomik dosya dönüşümü gerektirdiğini belirten anlamsal bayrak'");
        DB::statement("COMMENT ON COLUMN leads.converted_deal_id IS 'Fırsatın dönüştüğü tek dosya; unique bağ ikinci dönüşümü veritabanında engeller'");
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS enforce_lead_status_requirements() CASCADE');

        Schema::table('leads', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('converted_deal_id');
        });
        Schema::table('statuses', static function (Blueprint $table): void {
            $table->dropColumn(['required_fields', 'is_initial', 'converts_to_deal']);
        });
        Schema::table('contacts', static function (Blueprint $table): void {
            $table->dropColumn('data_source');
        });
    }
};
