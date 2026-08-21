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
        Schema::create('service_workflows', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('service_workflow_steps', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('service_workflow_id')->index()->constrained('service_workflows')->restrictOnDelete();
            $table->enum('type', ['action', 'waiting', 'decision']);
            $table->string('title');
            $table->text('guidance');
            $table->text('attention_note')->nullable();
            $table->unsignedInteger('sort_order');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['service_workflow_id', 'is_active', 'sort_order'], 'service_workflow_steps_lookup');
        });

        Schema::table('program_versions', static function (Blueprint $table): void {
            $table->foreignId('service_workflow_id')
                ->nullable()
                ->after('program_id')
                ->index()
                ->constrained('service_workflows')
                ->restrictOnDelete();
            $table->jsonb('workflow_snapshot')->nullable()->after('description');
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER service_workflows_no_delete
            BEFORE DELETE ON service_workflows
            FOR EACH ROW EXECUTE FUNCTION prevent_collaboration_delete();

            CREATE TRIGGER service_workflow_steps_no_delete
            BEFORE DELETE ON service_workflow_steps
            FOR EACH ROW EXECUTE FUNCTION prevent_collaboration_delete();

            CREATE TRIGGER service_workflows_audit
            AFTER INSERT OR UPDATE ON service_workflows
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');

            CREATE TRIGGER service_workflow_steps_audit
            AFTER INSERT OR UPDATE ON service_workflow_steps
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');
            SQL);

        DB::statement("COMMENT ON TABLE service_workflows IS 'Hizmetlerden bağımsız, yeniden kullanılabilir operasyon rehberleri'");
        DB::statement("COMMENT ON TABLE service_workflow_steps IS 'Silinmeyen, sıralı hizmet yürütme aşamaları; kaldırılan aşama pasifleştirilir'");
        DB::statement("COMMENT ON COLUMN program_versions.workflow_snapshot IS 'Dosya açıldığı dönemde geçerli hizmet akışının değişmez okunabilir anlık görüntüsü'");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS service_workflow_steps_audit ON service_workflow_steps');
        DB::statement('DROP TRIGGER IF EXISTS service_workflows_audit ON service_workflows');
        DB::statement('DROP TRIGGER IF EXISTS service_workflow_steps_no_delete ON service_workflow_steps');
        DB::statement('DROP TRIGGER IF EXISTS service_workflows_no_delete ON service_workflows');

        Schema::table('program_versions', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('service_workflow_id');
            $table->dropColumn('workflow_snapshot');
        });

        Schema::dropIfExists('service_workflow_steps');
        Schema::dropIfExists('service_workflows');
    }
};
