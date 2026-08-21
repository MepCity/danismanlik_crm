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
        Schema::create('email_templates', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->string('name')->unique();
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER email_templates_no_delete
            BEFORE DELETE ON email_templates
            FOR EACH ROW EXECUTE FUNCTION prevent_collaboration_delete();

            CREATE TRIGGER email_templates_audit
            AFTER INSERT OR UPDATE ON email_templates
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');
            SQL);
        DB::statement("COMMENT ON TABLE email_templates IS 'Filtreli toplu gönderimlerde kullanılan, silinmeden pasifleştirilen pazarlama e-posta metinleri'");

        DB::table('permissions')->updateOrInsert(
            ['name' => 'page.email_templates', 'guard_name' => 'web'],
            ['is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS email_templates_audit ON email_templates');
        DB::statement('DROP TRIGGER IF EXISTS email_templates_no_delete ON email_templates');
        Schema::dropIfExists('email_templates');
        DB::table('permissions')->where('name', 'page.email_templates')->update(['is_active' => false, 'updated_at' => now()]);
    }
};
