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
        Schema::create('teams', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->string('name')->unique();
            $table->foreignId('manager_id')->index()->constrained('users')->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('team_members', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('team_id')->index()->constrained('teams')->restrictOnDelete();
            $table->foreignId('user_id')->index()->constrained('users')->restrictOnDelete();
            $table->enum('role', ['member', 'manager'])->default('member')->index();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        Schema::create('role_permission_history', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->enum('subject_type', ['user', 'role']);
            $table->unsignedBigInteger('subject_id');
            $table->string('change_type');
            $table->jsonb('old_value')->nullable();
            $table->jsonb('new_value')->nullable();
            $table->foreignId('changed_by')->index()->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['change_type', 'created_at']);
        });

        Schema::create('break_glass_grants', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('user_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('granted_by')->index()->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE role_permission_history ADD CONSTRAINT role_permission_history_reason_not_blank CHECK (length(btrim(reason)) > 0)');
        DB::statement('ALTER TABLE break_glass_grants ADD CONSTRAINT break_glass_grants_reason_not_blank CHECK (length(btrim(reason)) > 0)');
        DB::statement('ALTER TABLE break_glass_grants ADD CONSTRAINT break_glass_grants_expiry_after_creation CHECK (expires_at > created_at)');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_role_permission_history_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'role_permission_history is append-only';
            END;
            $$;

            CREATE TRIGGER role_permission_history_append_only
            BEFORE UPDATE OR DELETE ON role_permission_history
            FOR EACH ROW
            EXECUTE FUNCTION prevent_role_permission_history_mutation();
            SQL);

        DB::statement("COMMENT ON TABLE teams IS 'Satır görünürlüğü kapsamı için operasyon ekipleri'");
        DB::statement("COMMENT ON TABLE team_members IS 'Kullanıcıların ekip üyelikleri ve ekip içi rol kodları'");
        DB::statement("COMMENT ON COLUMN team_members.role IS 'Ekip rolü: member veya manager'");
        DB::statement("COMMENT ON TABLE role_permission_history IS 'Yetki ve rol değişikliklerinin salt-ekleme geçmişi; UPDATE ve DELETE yasaktır'");
        DB::statement("COMMENT ON COLUMN role_permission_history.subject_type IS 'Değişiklik öznesi kodu: user veya role'");
        DB::statement("COMMENT ON TABLE break_glass_grants IS 'Sistem yöneticisinin gerekçeli ve süreli acil iş verisi erişimi'");
    }

    public function down(): void
    {
        Schema::dropIfExists('break_glass_grants');
        Schema::dropIfExists('role_permission_history');
        DB::statement('DROP FUNCTION IF EXISTS prevent_role_permission_history_mutation()');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};
