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
        Schema::create('statuses', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->string('code');
            $table->string('label');
            $table->enum('type', ['lead', 'deal']);
            $table->string('color');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['type', 'code']);
            $table->index(['type', 'is_active', 'sort_order']);
        });

        Schema::create('transitions', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('from_status_id')->index()->constrained('statuses')->restrictOnDelete();
            $table->foreignId('to_status_id')->index()->constrained('statuses')->restrictOnDelete();
            $table->string('required_permission')->nullable();
            $table->jsonb('condition')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['from_status_id', 'to_status_id']);
        });

        Schema::create('workflow_revisions', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->jsonb('snapshot');
            $table->timestamp('effective_from')->index();
            $table->foreignId('changed_by')->index()->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });

        $this->replaceTemporaryStatuses();

        Schema::create('status_history', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('lead_id')->nullable()->index()->constrained('leads')->restrictOnDelete();
            $table->foreignId('deal_id')->nullable()->index()->constrained('deals')->restrictOnDelete();
            $table->foreignId('status_id')->index()->constrained('statuses')->restrictOnDelete();
            $table->string('status_label_snapshot');
            $table->foreignId('workflow_revision_id')->nullable()->index()->constrained('workflow_revisions')->restrictOnDelete();
            $table->foreignId('transition_id')->nullable()->index()->constrained('transitions')->restrictOnDelete();
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->foreignId('changed_by')->index()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE transitions ADD CONSTRAINT transitions_distinct_statuses CHECK (from_status_id <> to_status_id)');
        DB::statement('ALTER TABLE workflow_revisions ADD CONSTRAINT workflow_revisions_reason_not_blank CHECK (length(btrim(reason)) > 0)');
        DB::statement('ALTER TABLE status_history ADD CONSTRAINT status_history_exactly_one_subject CHECK (num_nonnulls(lead_id, deal_id) = 1)');
        DB::statement('ALTER TABLE status_history ADD CONSTRAINT status_history_exit_not_before_entry CHECK (exited_at IS NULL OR exited_at >= entered_at)');
        DB::statement('CREATE UNIQUE INDEX status_history_one_open_lead ON status_history (lead_id) WHERE lead_id IS NOT NULL AND exited_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX status_history_one_open_deal ON status_history (deal_id) WHERE deal_id IS NOT NULL AND exited_at IS NULL');
        DB::statement('CREATE INDEX status_history_deal_timeline ON status_history (deal_id, entered_at DESC) WHERE deal_id IS NOT NULL');
        DB::statement('CREATE INDEX status_history_lead_timeline ON status_history (lead_id, entered_at DESC) WHERE lead_id IS NOT NULL');

        $this->createProtectionTriggers();
        $this->addComments();
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS prevent_workflow_row_delete() CASCADE');
        DB::statement('DROP FUNCTION IF EXISTS protect_status_code() CASCADE');
        DB::statement('DROP FUNCTION IF EXISTS prevent_workflow_revision_mutation() CASCADE');

        Schema::dropIfExists('status_history');
        $this->restoreTemporaryStatuses();
        Schema::dropIfExists('workflow_revisions');
        Schema::dropIfExists('transitions');
        Schema::dropIfExists('statuses');
    }

    private function replaceTemporaryStatuses(): void
    {
        DB::statement('ALTER TABLE leads DROP CONSTRAINT leads_lost_reason_required');
        DB::statement('ALTER TABLE leads DROP CONSTRAINT leads_callback_date_required');

        Schema::table('leads', static function (Blueprint $table): void {
            $table->dropColumn('status');
            $table->foreignId('status_id')->index()->constrained('statuses')->restrictOnDelete();
        });

        Schema::table('deals', static function (Blueprint $table): void {
            $table->dropColumn('status');
            $table->foreignId('status_id')->index()->constrained('statuses')->restrictOnDelete();
        });
    }

    private function restoreTemporaryStatuses(): void
    {
        Schema::table('leads', static function (Blueprint $table): void {
            $table->string('status')->nullable()->index();
        });
        DB::statement('UPDATE leads SET status = statuses.code FROM statuses WHERE leads.status_id = statuses.id');
        DB::statement('ALTER TABLE leads ALTER COLUMN status SET NOT NULL');
        Schema::table('leads', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('status_id');
        });
        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_lost_reason_required CHECK (status <> 'lost' OR (lost_reason IS NOT NULL AND length(btrim(lost_reason)) > 0))");
        DB::statement("ALTER TABLE leads ADD CONSTRAINT leads_callback_date_required CHECK (status <> 'callback' OR next_call_at IS NOT NULL)");

        Schema::table('deals', static function (Blueprint $table): void {
            $table->string('status')->nullable()->index();
        });
        DB::statement('UPDATE deals SET status = statuses.code FROM statuses WHERE deals.status_id = statuses.id');
        DB::statement('ALTER TABLE deals ALTER COLUMN status SET NOT NULL');
        Schema::table('deals', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('status_id');
        });
    }

    private function createProtectionTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_workflow_row_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION '% cannot be deleted; deactivate it instead', TG_TABLE_NAME;
            END;
            $$;

            CREATE TRIGGER statuses_prevent_delete
            BEFORE DELETE ON statuses
            FOR EACH ROW
            EXECUTE FUNCTION prevent_workflow_row_delete();

            CREATE TRIGGER transitions_prevent_delete
            BEFORE DELETE ON transitions
            FOR EACH ROW
            EXECUTE FUNCTION prevent_workflow_row_delete();

            CREATE TRIGGER status_history_prevent_delete
            BEFORE DELETE ON status_history
            FOR EACH ROW
            EXECUTE FUNCTION prevent_workflow_row_delete();

            CREATE OR REPLACE FUNCTION protect_status_code()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF NEW.code IS DISTINCT FROM OLD.code THEN
                    RAISE EXCEPTION 'statuses.code is immutable';
                END IF;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER statuses_protect_code
            BEFORE UPDATE ON statuses
            FOR EACH ROW
            EXECUTE FUNCTION protect_status_code();

            CREATE OR REPLACE FUNCTION prevent_workflow_revision_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'workflow_revisions is append-only';
            END;
            $$;

            CREATE TRIGGER workflow_revisions_append_only
            BEFORE UPDATE OR DELETE ON workflow_revisions
            FOR EACH ROW
            EXECUTE FUNCTION prevent_workflow_revision_mutation();
            SQL);
    }

    private function addComments(): void
    {
        DB::statement("COMMENT ON TABLE statuses IS 'Kodda enum olmayan, ayar kaydı olarak yönetilen fırsat ve dosya statüleri; silinmez, is_active=false ile pasifleştirilir'");
        DB::statement("COMMENT ON COLUMN statuses.code IS 'Tip içinde tekil ve değişmez makine kodu; kullanıcı etiketi label alanındadır'");
        DB::statement("COMMENT ON COLUMN statuses.label IS 'Deploy gerektirmeden yönetilen kullanıcı etiketi; K-05 gereği uygulama çeviri dosyası istisnasıdır'");
        DB::statement("COMMENT ON TABLE transitions IS 'İzin, koşul ve yan etki kancalarını tanımlayan iş akışı geçişleri; kancalar WP-09 StatusMachine tarafından uygulanacaktır'");
        DB::statement("COMMENT ON COLUMN transitions.required_permission IS 'WP-09 izin (guard) kancası; bu migration yalnızca şemayı sağlar'");
        DB::statement("COMMENT ON COLUMN transitions.condition IS 'WP-09 JSONB koşul kancası; bu migration koşulu değerlendirmez'");
        DB::statement("COMMENT ON COLUMN transitions.is_active IS 'Pasifleştirme sonrası yetim geçiş kontrolü ve yan etkiler WP-09 kapsamında uygulanacaktır'");
        DB::statement("COMMENT ON TABLE workflow_revisions IS 'K-09 gereği statü ve geçiş kümesinin değişmez, salt-ekleme anlık görüntüleri'");
        DB::statement("COMMENT ON TABLE status_history IS 'Statü sürelerinin gerçek kaynağı; exited_at çıkışta güncellenebilir, satırlar silinemez'");
        DB::statement("COMMENT ON COLUMN status_history.status_label_snapshot IS 'Geçmiş, statü etiketi sonradan değişse bile okunabilsin diye giriş anındaki etiket'");
        DB::statement("COMMENT ON COLUMN deals.status_changed_at IS 'Yalnızca pano sorgusu için denormalize önbellek; gerçek kaynak status_history tablosudur'");
        DB::statement("COMMENT ON COLUMN leads.status_id IS 'WP-07A ile geçici statü kodunun yerine geçen statuses FK bağı'");
        DB::statement("COMMENT ON COLUMN deals.status_id IS 'WP-07A ile geçici statü kodunun yerine geçen statuses FK bağı'");
    }
};
