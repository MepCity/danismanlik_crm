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
            $table->string('decision_role')->nullable()->after('title');
        });
        Schema::table('leads', static function (Blueprint $table): void {
            $table->foreignId('primary_contact_id')->nullable()->after('company_id')->index()->constrained('contacts')->restrictOnDelete();
        });
        Schema::table('interactions', static function (Blueprint $table): void {
            $table->foreignId('contact_id')->nullable()->after('deal_id')->index()->constrained('contacts')->restrictOnDelete();
        });

        foreach (['activities', 'comments', 'tasks', 'notifications'] as $table) {
            Schema::table($table, static function (Blueprint $blueprint): void {
                $blueprint->foreignId('company_id')->nullable()->after('id')->index()->constrained('companies')->restrictOnDelete();
            });
        }

        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_exactly_one_subject');
        DB::statement('ALTER TABLE comments DROP CONSTRAINT comments_exactly_one_subject');
        DB::statement('ALTER TABLE tasks DROP CONSTRAINT tasks_exactly_one_subject');
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT notifications_at_most_one_subject');
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_exactly_one_subject CHECK (num_nonnulls(company_id, lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE comments ADD CONSTRAINT comments_exactly_one_subject CHECK (num_nonnulls(company_id, lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_exactly_one_subject CHECK (num_nonnulls(company_id, lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_at_most_one_subject CHECK (num_nonnulls(company_id, lead_id, deal_id, deal_document_id) <= 1)');
        DB::statement("ALTER TABLE contacts ADD CONSTRAINT contacts_decision_role_valid CHECK (decision_role IS NULL OR decision_role IN ('decision_maker', 'authorized_contact', 'technical_contact', 'financial_contact', 'information_provider', 'other'))");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION validate_crm_contact_company()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                expected_company_id bigint;
                actual_company_id bigint;
            BEGIN
                IF TG_TABLE_NAME = 'leads' THEN
                    IF NEW.primary_contact_id IS NULL THEN
                        RETURN NEW;
                    END IF;
                    expected_company_id := NEW.company_id;
                    SELECT company_id INTO actual_company_id FROM contacts WHERE id = NEW.primary_contact_id;
                ELSE
                    IF NEW.contact_id IS NULL THEN
                        RETURN NEW;
                    END IF;
                    SELECT company_id INTO actual_company_id FROM contacts WHERE id = NEW.contact_id;
                    IF NEW.lead_id IS NOT NULL THEN
                        SELECT company_id INTO expected_company_id FROM leads WHERE id = NEW.lead_id;
                    ELSE
                        SELECT company_id INTO expected_company_id FROM deals WHERE id = NEW.deal_id;
                    END IF;
                END IF;

                IF actual_company_id IS DISTINCT FROM expected_company_id THEN
                    RAISE EXCEPTION 'contact must belong to the subject company';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE CONSTRAINT TRIGGER leads_primary_contact_company
            AFTER INSERT OR UPDATE OF company_id, primary_contact_id ON leads
            DEFERRABLE INITIALLY IMMEDIATE
            FOR EACH ROW EXECUTE FUNCTION validate_crm_contact_company();

            CREATE CONSTRAINT TRIGGER interactions_contact_company
            AFTER INSERT OR UPDATE OF lead_id, deal_id, contact_id ON interactions
            DEFERRABLE INITIALLY IMMEDIATE
            FOR EACH ROW EXECUTE FUNCTION validate_crm_contact_company();
            SQL);

        DB::statement("COMMENT ON COLUMN contacts.decision_role IS 'Kişinin bu satın alma kararındaki rolü; şirket içi unvanından ayrıdır'");
        DB::statement("COMMENT ON COLUMN leads.primary_contact_id IS 'Bu fırsat için görüşülen ana kişi; şirketin genel birincil kişisinden bağımsızdır'");
        DB::statement("COMMENT ON COLUMN interactions.contact_id IS 'Görüşmenin gerçekten yapıldığı kişi; geçmiş okunabilirliği için açık FK'");
        DB::statement("COMMENT ON COLUMN comments.company_id IS 'Firma geneli notları fırsat ve proje notlarından ayrı tutar'");
        DB::statement("CREATE TRIGGER companies_audit AFTER INSERT OR UPDATE OR DELETE ON companies FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]')");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS companies_audit ON companies');
        DB::statement('DROP TRIGGER IF EXISTS interactions_contact_company ON interactions');
        DB::statement('DROP TRIGGER IF EXISTS leads_primary_contact_company ON leads');
        DB::statement('DROP FUNCTION IF EXISTS validate_crm_contact_company()');
        DB::statement('ALTER TABLE contacts DROP CONSTRAINT IF EXISTS contacts_decision_role_valid');

        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_exactly_one_subject');
        DB::statement('ALTER TABLE comments DROP CONSTRAINT comments_exactly_one_subject');
        DB::statement('ALTER TABLE tasks DROP CONSTRAINT tasks_exactly_one_subject');
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT notifications_at_most_one_subject');
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_exactly_one_subject CHECK (num_nonnulls(lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE comments ADD CONSTRAINT comments_exactly_one_subject CHECK (num_nonnulls(lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_exactly_one_subject CHECK (num_nonnulls(lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_at_most_one_subject CHECK (num_nonnulls(lead_id, deal_id, deal_document_id) <= 1)');

        foreach (['notifications', 'tasks', 'comments', 'activities'] as $table) {
            Schema::table($table, static function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('company_id');
            });
        }
        Schema::table('interactions', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contact_id');
        });
        Schema::table('leads', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('primary_contact_id');
        });
        Schema::table('contacts', static function (Blueprint $table): void {
            $table->dropColumn('decision_role');
        });
    }
};
