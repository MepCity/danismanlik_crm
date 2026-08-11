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
        Schema::table('deal_documents', static function (Blueprint $table): void {
            $table->boolean('condition_matches')->nullable()->after('condition_snapshot');
        });

        Schema::create('document_requirement_suggestions', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('deal_document_id')->index()->constrained('deal_documents')->restrictOnDelete();
            $table->string('reason');
            $table->jsonb('reason_parameters');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'superseded'])->default('pending')->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE document_requirement_suggestions ADD CONSTRAINT document_requirement_suggestions_decision_complete CHECK ((status = 'pending' AND decided_by IS NULL AND decided_at IS NULL) OR (status IN ('accepted', 'rejected') AND decided_by IS NOT NULL AND decided_at IS NOT NULL) OR (status = 'superseded' AND decided_by IS NULL AND decided_at IS NOT NULL))");
        DB::statement("CREATE UNIQUE INDEX document_requirement_suggestions_one_pending ON document_requirement_suggestions (deal_document_id) WHERE status = 'pending'");

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER document_requirement_suggestions_audit
            AFTER INSERT OR UPDATE OR DELETE ON document_requirement_suggestions
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');

            CREATE OR REPLACE FUNCTION prevent_document_requirement_suggestion_deletion()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'document requirement suggestions cannot be deleted';
            END;
            $$;

            CREATE TRIGGER document_requirement_suggestions_no_delete
            BEFORE DELETE ON document_requirement_suggestions
            FOR EACH ROW
            EXECUTE FUNCTION prevent_document_requirement_suggestion_deletion();
            SQL);

        DB::statement("COMMENT ON COLUMN deal_documents.condition_matches IS 'Koşullu şablonun son değerlendirme sonucu; false durumunda yinelenen önerileri engeller, koşul yeniden sağlanırsa true olur'");
        DB::statement("COMMENT ON TABLE document_requirement_suggestions IS 'Koşulu kalkan belgeyi otomatik silmek yerine PM kararına sunan, silinmeyen öneri geçmişi'");
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS prevent_document_requirement_suggestion_deletion() CASCADE');
        Schema::dropIfExists('document_requirement_suggestions');

        Schema::table('deal_documents', static function (Blueprint $table): void {
            $table->dropColumn('condition_matches');
        });
    }
};
