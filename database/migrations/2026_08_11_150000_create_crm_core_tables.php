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
        Schema::create('companies', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->string('legal_name');
            $table->string('tax_number', 11)->nullable()->unique();
            $table->string('tax_office')->nullable();
            $table->string('nace_code')->nullable();
            $table->char('city', 2);
            $table->string('district')->nullable();
            $table->enum('size', ['micro', 'small', 'medium', 'large'])->nullable()->index();
            $table->unsignedInteger('employee_count')->nullable();
            $table->string('source')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('contacts', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('company_id')->index()->constrained('companies')->restrictOnDelete();
            $table->string('full_name');
            $table->string('title')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('consent_call')->nullable();
            $table->boolean('consent_sms')->nullable();
            $table->boolean('consent_email')->nullable();
            $table->boolean('do_not_call')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('communication_consents', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('contact_id')->index()->constrained('contacts')->restrictOnDelete();
            $table->enum('channel', ['call', 'sms', 'email']);
            $table->enum('purpose', ['marketing', 'service']);
            $table->enum('status', ['granted', 'denied', 'withdrawn']);
            $table->string('legal_basis');
            $table->enum('source', ['form', 'phone', 'list', 'referral', 'iys', 'other']);
            $table->date('disclosure_date')->nullable();
            $table->string('disclosure_method')->nullable();
            $table->jsonb('evidence')->nullable();
            $table->string('iys_reference')->nullable();
            $table->timestamp('effective_from');
            $table->foreignId('recorded_by')->index()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('leads', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('company_id')->index()->constrained('companies')->restrictOnDelete();
            $table->foreignId('owner_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('source')->nullable()->index();
            $table->string('interested_program')->nullable();
            $table->string('status')->index();
            $table->timestamp('next_call_at')->nullable()->index();
            $table->text('lost_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('interactions', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->enum('subject_type', ['lead', 'deal']);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('user_id')->index()->constrained('users')->restrictOnDelete();
            $table->enum('type', ['call', 'meeting', 'email', 'other']);
            $table->timestamp('occurred_at');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('outcome')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_tax_number_format CHECK (tax_number IS NULL OR tax_number ~ '^[0-9]{10}([0-9])?$')");
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_city_code CHECK (city ~ '^(0[1-9]|[1-7][0-9]|8[01])$')");
        DB::statement('CREATE UNIQUE INDEX contacts_one_primary_per_company ON contacts (company_id) WHERE is_primary = true');
        DB::statement('CREATE INDEX communication_consents_current_lookup ON communication_consents (contact_id, channel, purpose, effective_from DESC)');
        DB::statement('CREATE INDEX interactions_subject_timeline ON interactions (subject_type, subject_id, occurred_at DESC)');
        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_lost_reason_required CHECK (status <> 'lost' OR (lost_reason IS NOT NULL AND length(btrim(lost_reason)) > 0))");
        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_callback_date_required CHECK (status <> 'callback' OR next_call_at IS NOT NULL)");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_communication_consent_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'communication_consents is append-only';
            END;
            $$;

            CREATE TRIGGER communication_consents_append_only
            BEFORE UPDATE OR DELETE ON communication_consents
            FOR EACH ROW
            EXECUTE FUNCTION prevent_communication_consent_mutation();
            SQL);

        DB::statement("COMMENT ON TABLE companies IS 'Teşvik ve hibe süreçlerinde takip edilen firmalar'");
        DB::statement("COMMENT ON COLUMN companies.tax_number IS 'Varsa 10 haneli VKN veya 11 haneli TCKN; tekildir'");
        DB::statement("COMMENT ON COLUMN companies.city IS 'Koşullu evrak kurallarında kullanılan 01-81 arası iki haneli il plaka kodu'");
        DB::statement("COMMENT ON TABLE contacts IS 'Firmaların yetkili ve iletişim kişileri'");
        DB::statement("COMMENT ON COLUMN contacts.consent_call IS 'Yalnızca hızlı sorgu için güncel özet; gerçek kaynak communication_consents tablosudur ve doğrudan senkronizasyon WP-12/13 kapsamındadır'");
        DB::statement("COMMENT ON COLUMN contacts.consent_sms IS 'Yalnızca hızlı sorgu için güncel özet; gerçek kaynak communication_consents tablosudur ve doğrudan senkronizasyon WP-12/13 kapsamındadır'");
        DB::statement("COMMENT ON COLUMN contacts.consent_email IS 'Yalnızca hızlı sorgu için güncel özet; gerçek kaynak communication_consents tablosudur ve doğrudan senkronizasyon WP-12/13 kapsamındadır'");
        DB::statement("COMMENT ON COLUMN contacts.do_not_call IS 'Yalnızca hızlı sorgu için güncel özet; gerçek kaynak communication_consents tablosudur ve doğrudan senkronizasyon WP-12/13 kapsamındadır'");
        DB::statement("COMMENT ON TABLE communication_consents IS 'KVKK ve İYS için salt-ekleme iletişim izin defteri; UPDATE ve DELETE yasaktır'");
        DB::statement("COMMENT ON COLUMN communication_consents.effective_from IS 'Kanal ve amaç bazında güncel kaydı belirleyen yürürlük zamanı'");
        DB::statement("COMMENT ON TABLE leads IS 'Pazarlama fırsatları; statü ve program bağları geçici olarak kod/metin taşır'");
        DB::statement("COMMENT ON COLUMN leads.status IS 'WP-07 statuses bağı kurulana kadar geçici fırsat statüsü kodu'");
        DB::statement("COMMENT ON COLUMN leads.interested_program IS 'WP-06 program_versions bağı kurulana kadar geçici program metni veya kodu'");
        DB::statement("COMMENT ON TABLE interactions IS 'Fırsat veya dosyadan ayrı tutulan kontrollü polymorphic görüşme kayıtları'");
        DB::statement("COMMENT ON COLUMN interactions.subject_type IS 'Kontrollü özne kodu: lead veya deal; deal bütünlüğü WP-06 sonrasında bağlanacaktır'");
    }

    public function down(): void
    {
        Schema::dropIfExists('interactions');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('communication_consents');
        DB::statement('DROP FUNCTION IF EXISTS prevent_communication_consent_mutation()');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('companies');
    }
};
