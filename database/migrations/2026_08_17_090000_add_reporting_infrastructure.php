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
        Schema::table('statuses', static function (Blueprint $table): void {
            $table->boolean('awaits_customer_response')->default(false)->index();
        });

        Schema::table('deals', static function (Blueprint $table): void {
            $table->string('result_outcome')->nullable()->index();
        });
        DB::statement("ALTER TABLE deals ADD CONSTRAINT deals_result_outcome_allowed CHECK (result_outcome IS NULL OR result_outcome IN ('approved', 'rejected'))");

        DB::statement('CREATE INDEX status_history_deal_status_duration ON status_history (deal_id, status_id, entered_at) INCLUDE (exited_at) WHERE deal_id IS NOT NULL');

        Schema::create('report_exports', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('report_code');
            $table->unsignedBigInteger('row_count');
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_code_allowed CHECK (report_code IN ('deal_board', 'pending_assignments', 'pm_workload', 'missing_documents', 'upcoming_deadlines', 'conversion_funnel'))");
        DB::statement('CREATE INDEX report_exports_actor_timeline ON report_exports (actor_id, created_at DESC)');
        DB::statement("CREATE TRIGGER report_exports_audit AFTER INSERT ON report_exports FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]')");
        DB::statement('CREATE TRIGGER report_exports_append_only BEFORE UPDATE OR DELETE ON report_exports FOR EACH ROW EXECUTE FUNCTION prevent_collaboration_delete()');

        DB::statement("COMMENT ON COLUMN statuses.awaits_customer_response IS 'Etikete bağlı string karşılaştırması yapmadan müşteri dönüşü bekleyen statüleri raporlayan anlamsal bayrak'");
        DB::statement("COMMENT ON COLUMN deals.result_outcome IS 'Program başarı oranının gerçek kaynağı; nullable approved veya rejected başvuru sonucu'");
        DB::statement("COMMENT ON TABLE report_exports IS 'Her Excel dışa aktarımının aktör, sabit rapor kodu ve satır sayısıyla tutulan salt-ekleme denetim izi'");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS report_exports_append_only ON report_exports');
        DB::statement('DROP TRIGGER IF EXISTS report_exports_audit ON report_exports');
        Schema::dropIfExists('report_exports');
        DB::statement('DROP INDEX IF EXISTS status_history_deal_status_duration');

        Schema::table('deals', static function (Blueprint $table): void {
            $table->dropColumn('result_outcome');
        });
        Schema::table('statuses', static function (Blueprint $table): void {
            $table->dropColumn('awaits_customer_response');
        });
    }
};
