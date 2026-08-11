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
        $this->createActivities();
        $this->createAuditLog();
        $this->createCollaborationTables();
        $this->createOutbox();
        $this->createProtectionAndAuditTriggers();
        $this->addComments();
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS write_audit_log() CASCADE');
        DB::statement('DROP FUNCTION IF EXISTS prevent_audit_log_mutation() CASCADE');
        DB::statement('DROP FUNCTION IF EXISTS prevent_activity_mutation() CASCADE');

        Schema::dropIfExists('outbox');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('activities');
    }

    private function createActivities(): void
    {
        Schema::create('activities', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->restrictOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->restrictOnDelete();
            $table->foreignId('deal_document_id')->nullable()->constrained('deal_documents')->restrictOnDelete();
            $table->string('action');
            $table->jsonb('payload');
            $table->enum('source', ['user', 'automation', 'integration', 'system']);
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_exactly_one_subject CHECK (num_nonnulls(lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('CREATE INDEX activities_deal_timeline ON activities (deal_id, created_at DESC) WHERE deal_id IS NOT NULL');
        DB::statement('CREATE INDEX activities_lead_timeline ON activities (lead_id, created_at DESC) WHERE lead_id IS NOT NULL');
        DB::statement('CREATE INDEX activities_actor_timeline ON activities (actor_id, created_at DESC) WHERE actor_id IS NOT NULL');
    }

    private function createAuditLog(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE audit_log (
                id bigint GENERATED ALWAYS AS IDENTITY,
                table_name text NOT NULL,
                row_id bigint NOT NULL,
                operation text NOT NULL CHECK (operation IN ('INSERT', 'UPDATE', 'DELETE')),
                old_data jsonb,
                new_data jsonb,
                actor_id bigint,
                session_id text,
                client_ip inet,
                source text NOT NULL CHECK (source IN ('user', 'automation', 'integration', 'system')),
                db_user text NOT NULL DEFAULT current_user,
                statement_timestamp timestamptz NOT NULL DEFAULT statement_timestamp(),
                created_at timestamptz NOT NULL DEFAULT clock_timestamp(),
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
            SQL);

        for ($offset = 0; $offset <= 3; $offset++) {
            $start = now('UTC')->startOfMonth()->addMonths($offset);
            $end = $start->copy()->addMonth();
            $name = 'audit_log_'.$start->format('Ym');

            DB::statement(sprintf(
                "CREATE TABLE %s PARTITION OF audit_log FOR VALUES FROM ('%s') TO ('%s')",
                $name,
                $start->toIso8601String(),
                $end->toIso8601String(),
            ));
        }

        DB::statement('CREATE INDEX audit_log_record_timeline ON audit_log (table_name, row_id, created_at DESC)');
        DB::statement('CREATE INDEX audit_log_actor_timeline ON audit_log (actor_id, created_at DESC) WHERE actor_id IS NOT NULL');
    }

    private function createCollaborationTables(): void
    {
        Schema::create('comments', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->restrictOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->restrictOnDelete();
            $table->foreignId('deal_document_id')->nullable()->constrained('deal_documents')->restrictOnDelete();
            $table->foreignId('user_id')->index()->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->jsonb('mentions')->default('[]');
            $table->enum('visibility', ['internal', 'customer'])->default('internal');
            $table->foreignId('parent_id')->nullable()->index();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
        });

        Schema::table('comments', static function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('comments')->restrictOnDelete();
        });

        Schema::create('tasks', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->restrictOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->restrictOnDelete();
            $table->foreignId('deal_document_id')->nullable()->constrained('deal_documents')->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('due_at');
            $table->timestamp('remind_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->foreignId('lead_id')->nullable()->constrained('leads')->restrictOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->restrictOnDelete();
            $table->foreignId('deal_document_id')->nullable()->constrained('deal_documents')->restrictOnDelete();
            $table->string('title');
            $table->text('body');
            $table->enum('channel', ['in_app', 'email', 'push']);
            $table->timestamp('read_at')->nullable();
            $table->enum('delivery_status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE comments ADD CONSTRAINT comments_exactly_one_subject CHECK (num_nonnulls(lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE comments ADD CONSTRAINT comments_body_not_blank CHECK (length(btrim(body)) > 0)');
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_exactly_one_subject CHECK (num_nonnulls(lead_id, deal_id, deal_document_id) = 1)');
        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_title_not_blank CHECK (length(btrim(title)) > 0)');
        DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_at_most_one_subject CHECK (num_nonnulls(lead_id, deal_id, deal_document_id) <= 1)');
        DB::statement('CREATE INDEX tasks_assignee_due ON tasks (assigned_to, due_at)');
        DB::statement('CREATE INDEX notifications_user_unread ON notifications (user_id, read_at)');
    }

    private function createOutbox(): void
    {
        Schema::create('outbox', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->string('event_name');
            $table->jsonb('payload');
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('available_at');
            $table->timestampTz('processed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement('CREATE INDEX outbox_unprocessed_available ON outbox (available_at, id) WHERE processed_at IS NULL');
    }

    private function createProtectionAndAuditTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_activity_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'activities is append-only';
            END;
            $$;

            CREATE TRIGGER activities_append_only
            BEFORE UPDATE OR DELETE ON activities
            FOR EACH ROW
            EXECUTE FUNCTION prevent_activity_mutation();

            CREATE OR REPLACE FUNCTION prevent_audit_log_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'audit_log is append-only';
            END;
            $$;

            CREATE TRIGGER audit_log_append_only
            BEFORE UPDATE OR DELETE ON audit_log
            FOR EACH ROW
            EXECUTE FUNCTION prevent_audit_log_mutation();

            CREATE OR REPLACE FUNCTION write_audit_log()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            DECLARE
                excluded_fields text[];
                safe_old jsonb;
                safe_new jsonb;
                old_delta jsonb;
                new_delta jsonb;
                audit_actor_id bigint;
                audit_source text;
            BEGIN
                IF TG_NARGS <> 1 THEN
                    RAISE EXCEPTION 'write_audit_log requires an explicit JSON exclusion list';
                END IF;

                SELECT coalesce(array_agg(value), ARRAY[]::text[])
                INTO excluded_fields
                FROM jsonb_array_elements_text(TG_ARGV[0]::jsonb);

                safe_old := CASE WHEN TG_OP IN ('UPDATE', 'DELETE') THEN to_jsonb(OLD) - excluded_fields ELSE NULL END;
                safe_new := CASE WHEN TG_OP IN ('INSERT', 'UPDATE') THEN to_jsonb(NEW) - excluded_fields ELSE NULL END;

                IF TG_OP = 'UPDATE' THEN
                    SELECT coalesce(jsonb_object_agg(key, value), '{}'::jsonb)
                    INTO old_delta
                    FROM jsonb_each(safe_old)
                    WHERE safe_new -> key IS DISTINCT FROM value;

                    SELECT coalesce(jsonb_object_agg(key, value), '{}'::jsonb)
                    INTO new_delta
                    FROM jsonb_each(safe_new)
                    WHERE safe_old -> key IS DISTINCT FROM value;
                ELSE
                    old_delta := safe_old;
                    new_delta := safe_new;
                END IF;

                audit_actor_id := nullif(current_setting('app.actor_id', true), '')::bigint;
                audit_source := coalesce(nullif(current_setting('app.source', true), ''), 'system');

                IF audit_source NOT IN ('user', 'automation', 'integration', 'system') THEN
                    audit_source := 'system';
                END IF;

                EXECUTE format(
                    'INSERT INTO %I.audit_log (
                        table_name, row_id, operation, old_data, new_data,
                        actor_id, session_id, client_ip, source
                    ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)',
                    TG_TABLE_SCHEMA
                ) USING
                    TG_TABLE_NAME,
                    coalesce(safe_new ->> 'id', safe_old ->> 'id')::bigint,
                    TG_OP,
                    old_delta,
                    new_delta,
                    audit_actor_id,
                    nullif(current_setting('app.session_id', true), ''),
                    nullif(current_setting('app.client_ip', true), '')::inet,
                    audit_source;

                RETURN coalesce(NEW, OLD);
            END;
            $$;

            CREATE TRIGGER users_audit
            AFTER INSERT OR UPDATE OR DELETE ON users
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('["password","remember_token","api_token","two_factor_secret","two_factor_recovery_codes","signed_url_secret","e_signature_password"]');

            CREATE TRIGGER contacts_audit
            AFTER INSERT OR UPDATE OR DELETE ON contacts
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');

            CREATE TRIGGER leads_audit
            AFTER INSERT OR UPDATE OR DELETE ON leads
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');

            CREATE TRIGGER deals_audit
            AFTER INSERT OR UPDATE OR DELETE ON deals
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');

            CREATE TRIGGER deal_documents_audit
            AFTER INSERT OR UPDATE OR DELETE ON deal_documents
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');

            CREATE TRIGGER files_audit
            AFTER INSERT OR UPDATE OR DELETE ON files
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');

            CREATE TRIGGER comments_audit
            AFTER INSERT OR UPDATE OR DELETE ON comments
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');
            SQL);
    }

    private function addComments(): void
    {
        DB::statement("COMMENT ON TABLE activities IS 'Kullanıcıya gösterilen değişmez olay akışı; ham DB diff audit_log tablosunda kalır'");
        DB::statement("COMMENT ON COLUMN activities.payload IS 'Ham diff değil; olay tipiyle uyumlu parametreler ve olay anındaki kullanıcı etiketlerinin anlık görüntüsü. Silinmiş veya yeniden adlandırılmış statüler geçmişi okunamaz hâle getirmez'");
        DB::statement("COMMENT ON TABLE audit_log IS 'DB trigger tabanlı, hassas alanlardan arındırılmış JSONB değişiklik defteri; aylık partition ve salt-ekleme'");
        DB::statement("COMMENT ON COLUMN audit_log.source IS 'Aktör bağlamı yoksa kasıtlı olarak system; bilinmeyen kaynağın bilinmediği korunur'");
        DB::statement("COMMENT ON TABLE comments IS 'Fırsat, dosya veya belgeye bağlı; düzenleme geçmişi audit_log içinde korunan yorumlar'");
        DB::statement("COMMENT ON COLUMN comments.visibility IS 'Müşteri portalı Faz 3 öncesinde geçmiş görünürlüğünü sınıflandırır'");
        DB::statement("COMMENT ON TABLE outbox IS 'İç domain olaylarının iş verisiyle aynı transaction içinde kalıcılaştırıldığı kuyruk; dış webhook değildir'");
    }
};
