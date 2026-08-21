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
        Schema::table('program_versions', static function (Blueprint $table): void {
            $table->string('call_period')->nullable()->change();
        });

        foreach (['activities', 'comments', 'tasks', 'notifications'] as $tableName) {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->foreignId('program_id')->nullable()->index()->constrained('programs')->restrictOnDelete();
            });
        }

        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_exactly_one_subject');
        DB::statement('ALTER TABLE comments DROP CONSTRAINT comments_exactly_one_subject');
        DB::statement('ALTER TABLE tasks DROP CONSTRAINT tasks_exactly_one_subject');
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT notifications_at_most_one_subject');
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_exactly_one_subject CHECK (num_nonnulls(company_id, program_id, lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE comments ADD CONSTRAINT comments_exactly_one_subject CHECK (num_nonnulls(company_id, program_id, lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_exactly_one_subject CHECK (num_nonnulls(company_id, program_id, lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_at_most_one_subject CHECK (num_nonnulls(company_id, program_id, lead_id, deal_id, deal_document_id) <= 1)');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER programs_audit
            AFTER INSERT OR UPDATE ON programs
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');

            CREATE TRIGGER program_versions_audit
            AFTER INSERT OR UPDATE ON program_versions
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');
            SQL);

        DB::statement("COMMENT ON COLUMN comments.program_id IS 'Çağrı dönemlerinden bağımsız hizmet kimliğine bağlı ekip yorumu'");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS program_versions_audit ON program_versions');
        DB::statement('DROP TRIGGER IF EXISTS programs_audit ON programs');
        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_exactly_one_subject');
        DB::statement('ALTER TABLE comments DROP CONSTRAINT comments_exactly_one_subject');
        DB::statement('ALTER TABLE tasks DROP CONSTRAINT tasks_exactly_one_subject');
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT notifications_at_most_one_subject');

        foreach (['notifications', 'tasks', 'comments', 'activities'] as $tableName) {
            Schema::table($tableName, static function (Blueprint $table): void {
                $table->dropConstrainedForeignId('program_id');
            });
        }

        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_exactly_one_subject CHECK (num_nonnulls(company_id, lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE comments ADD CONSTRAINT comments_exactly_one_subject CHECK (num_nonnulls(company_id, lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_exactly_one_subject CHECK (num_nonnulls(company_id, lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_at_most_one_subject CHECK (num_nonnulls(company_id, lead_id, deal_id, deal_document_id) <= 1)');

        DB::statement("UPDATE program_versions SET call_period = 'Tanımsız dönem' WHERE call_period IS NULL");
        Schema::table('program_versions', static function (Blueprint $table): void {
            $table->string('call_period')->nullable(false)->change();
        });
    }
};
