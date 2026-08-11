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
        Schema::create('programs', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->string('name');
            $table->enum('institution', [
                'kosgeb',
                'tubitak',
                'sanayi_bakanligi',
                'kalkinma_ajansi',
                'ticaret_bakanligi',
                'other',
            ]);
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('program_versions', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('program_id')->index()->constrained('programs')->restrictOnDelete();
            $table->string('call_period');
            $table->timestamp('application_opens_at')->nullable();
            $table->timestamp('application_closes_at')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['program_id', 'call_period']);
        });

        Schema::create('doc_templates', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('program_version_id')->index()->constrained('program_versions')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->jsonb('condition')->nullable();
            $table->jsonb('accepted_formats');
            $table->unsignedInteger('validity_days')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['program_version_id', 'name']);
        });

        Schema::create('deals', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('company_id')->index()->constrained('companies')->restrictOnDelete();
            $table->foreignId('program_version_id')->index()->constrained('program_versions')->restrictOnDelete();
            $table->string('reference_no')->unique();
            $table->string('status')->index();
            $table->timestamp('status_changed_at')->index();
            $table->foreignId('pm_user_id')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('opened_by_user_id')->index()->constrained('users')->restrictOnDelete();
            $table->decimal('requested_amount', 18, 2)->nullable();
            $table->string('application_no')->nullable()->index();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->index();
            $table->timestamp('document_requested_at')->nullable();
            $table->timestamp('first_document_received_at')->nullable();
            $table->timestamp('all_required_accepted_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'program_version_id']);
        });

        Schema::create('deal_documents', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('deal_id')->index()->constrained('deals')->restrictOnDelete();
            $table->foreignId('source_doc_template_id')->nullable()->index()->constrained('doc_templates')->restrictOnDelete();
            $table->foreignId('source_program_version_id')->index()->constrained('program_versions')->restrictOnDelete();
            $table->string('name_snapshot');
            $table->text('description_snapshot')->nullable();
            $table->boolean('required_snapshot');
            $table->jsonb('condition_snapshot')->nullable();
            $table->enum('status', [
                'to_request',
                'requested',
                'uploaded',
                'under_review',
                'accepted',
                'rejected',
                'new_version_expected',
                'not_required',
                'expired',
            ]);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('validity_expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['deal_id', 'status']);
        });

        Schema::create('files', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('deal_document_id')->index()->constrained('deal_documents')->restrictOnDelete();
            $table->uuid('storage_key')->unique();
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->index();
            $table->unsignedInteger('version_no');
            $table->foreignId('uploaded_by')->index()->constrained('users')->restrictOnDelete();
            $table->enum('scan_result', ['pending', 'clean', 'infected', 'failed'])->default('pending')->index();
            $table->boolean('is_deleted')->default(false)->index();
            $table->timestamps();
            $table->unique(['deal_document_id', 'version_no']);
        });

        DB::statement('ALTER TABLE program_versions ADD CONSTRAINT program_versions_application_window CHECK (application_closes_at IS NULL OR application_opens_at IS NULL OR application_closes_at > application_opens_at)');
        DB::statement('ALTER TABLE files ADD CONSTRAINT files_size_positive CHECK (size_bytes > 0)');
        DB::statement('ALTER TABLE files ADD CONSTRAINT files_version_positive CHECK (version_no >= 1)');

        $this->closeCrmTemporaryLinks();
        $this->addComments();
    }

    public function down(): void
    {
        $this->restoreCrmTemporaryLinks();

        Schema::dropIfExists('files');
        Schema::dropIfExists('deal_documents');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('doc_templates');
        Schema::dropIfExists('program_versions');
        Schema::dropIfExists('programs');
    }

    private function closeCrmTemporaryLinks(): void
    {
        DB::statement('ALTER TABLE leads RENAME COLUMN interested_program TO interested_program_version_id');
        DB::statement('ALTER TABLE leads ALTER COLUMN interested_program_version_id TYPE bigint USING interested_program_version_id::bigint');
        DB::statement('CREATE INDEX leads_interested_program_version_id_index ON leads (interested_program_version_id)');
        DB::statement('ALTER TABLE leads ADD CONSTRAINT leads_interested_program_version_id_foreign FOREIGN KEY (interested_program_version_id) REFERENCES program_versions (id) ON DELETE RESTRICT');

        Schema::table('interactions', static function (Blueprint $table): void {
            $table->foreignId('lead_id')->nullable()->index()->constrained('leads')->restrictOnDelete();
            $table->foreignId('deal_id')->nullable()->index()->constrained('deals')->restrictOnDelete();
        });
        DB::statement("UPDATE interactions SET lead_id = subject_id WHERE subject_type = 'lead'");
        DB::statement("UPDATE interactions SET deal_id = subject_id WHERE subject_type = 'deal'");
        DB::statement('DROP INDEX interactions_subject_timeline');
        Schema::table('interactions', static function (Blueprint $table): void {
            $table->dropColumn(['subject_type', 'subject_id']);
        });
        DB::statement('ALTER TABLE interactions ADD CONSTRAINT interactions_exactly_one_subject CHECK (num_nonnulls(lead_id, deal_id) = 1)');
        DB::statement('CREATE INDEX interactions_lead_timeline ON interactions (lead_id, occurred_at DESC) WHERE lead_id IS NOT NULL');
        DB::statement('CREATE INDEX interactions_deal_timeline ON interactions (deal_id, occurred_at DESC) WHERE deal_id IS NOT NULL');

        DB::statement("COMMENT ON COLUMN leads.interested_program_version_id IS 'İlgilenilen çağrı sürümüne gerçek FK; WP-05 geçici metin bağı WP-06 ile kapatılmıştır'");
        DB::statement("COMMENT ON TABLE interactions IS 'Fırsat veya dosyaya tam olarak bir gerçek FK ile bağlı görüşme kayıtları'");
    }

    private function restoreCrmTemporaryLinks(): void
    {
        Schema::table('interactions', static function (Blueprint $table): void {
            $table->enum('subject_type', ['lead', 'deal'])->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
        });
        DB::statement("UPDATE interactions SET subject_type = CASE WHEN lead_id IS NOT NULL THEN 'lead' ELSE 'deal' END, subject_id = COALESCE(lead_id, deal_id)");
        Schema::table('interactions', static function (Blueprint $table): void {
            $table->dropForeign(['lead_id']);
            $table->dropForeign(['deal_id']);
            $table->dropColumn(['lead_id', 'deal_id']);
        });
        DB::statement('ALTER TABLE interactions ALTER COLUMN subject_type SET NOT NULL');
        DB::statement('ALTER TABLE interactions ALTER COLUMN subject_id SET NOT NULL');
        DB::statement('CREATE INDEX interactions_subject_timeline ON interactions (subject_type, subject_id, occurred_at DESC)');

        Schema::table('leads', static function (Blueprint $table): void {
            $table->dropForeign(['interested_program_version_id']);
            $table->dropIndex(['interested_program_version_id']);
        });
        DB::statement('ALTER TABLE leads ALTER COLUMN interested_program_version_id TYPE varchar(255) USING interested_program_version_id::varchar');
        DB::statement('ALTER TABLE leads RENAME COLUMN interested_program_version_id TO interested_program');
    }

    private function addComments(): void
    {
        DB::statement("COMMENT ON TABLE programs IS 'Yönetilebilir teşvik ve hibe programları'");
        DB::statement("COMMENT ON TABLE program_versions IS 'Program şablonu sözleşmesel anlık görüntüdür: bir çağrının evrak listesi dosyanın hukuki bağlamı olarak sabit kalır; sonraki çağrı değişiklikleri açık dosyaları bozmaz'");
        DB::statement("COMMENT ON COLUMN doc_templates.condition IS 'WP-10 koşul motorunun değerlendireceği örnek JSONB: {\"all\":[{\"field\":\"company.city\",\"op\":\"in\",\"value\":[\"01\",\"31\"]}]}'");
        DB::statement("COMMENT ON TABLE deals IS 'Firma ile program çağrısını bağlayan merkez operasyon dosyası; aynı firma ve çağrı için yeniden başvuru ayrı referans numarasıyla mümkündür'");
        DB::statement("COMMENT ON COLUMN deals.status IS 'WP-07 statuses bağı kurulana kadar geçici dosya statüsü kodu'");
        DB::statement("COMMENT ON COLUMN deals.status_changed_at IS 'Yalnızca pano sorguları için denormalize önbellek; gerçek kaynak WP-07 status_history tablosudur'");
        DB::statement("COMMENT ON TABLE deal_documents IS 'Program evrak şablonunun dosyaya kopyalanmış sözleşmesel anlık görüntüsü veya dosyaya özel ek belge talebi'");
        DB::statement("COMMENT ON COLUMN deal_documents.source_doc_template_id IS 'NULL ise PM tarafından yalnız bu dosyaya özel açılmış ek belge talebidir'");
        DB::statement("COMMENT ON COLUMN deal_documents.status IS 'Dokuz değerli belge akışı; not_required koşullu evrakı N/A, expired süresi dolmuş evrakı temsil eder'");
        DB::statement("COMMENT ON TABLE files IS 'Belge gereksinimine bağlı, silinmeden sürümlenen nesne deposu dosya metadatası'");
        DB::statement("COMMENT ON COLUMN files.storage_key IS 'Yalnız opaque UUID; deal_id ve orijinal ad içermez, böylece nesne deposu/CDN/hata günlüklarında firma ve dosya bilgisi sızmaz'");
    }
};
