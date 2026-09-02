<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE role_permission_history DROP CONSTRAINT IF EXISTS role_permission_history_subject_type_check');
        DB::statement("ALTER TABLE role_permission_history ADD CONSTRAINT role_permission_history_subject_type_check CHECK (((subject_type)::text = ANY ((ARRAY['user'::character varying, 'role'::character varying, 'team'::character varying])::text[])))");
        DB::statement("COMMENT ON COLUMN role_permission_history.subject_type IS 'Değişiklik öznesi kodu: user, role veya team'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE role_permission_history DROP CONSTRAINT IF EXISTS role_permission_history_subject_type_check');
        DB::statement("ALTER TABLE role_permission_history ADD CONSTRAINT role_permission_history_subject_type_check CHECK (((subject_type)::text = ANY ((ARRAY['user'::character varying, 'role'::character varying])::text[])))");
        DB::statement("COMMENT ON COLUMN role_permission_history.subject_type IS 'Değişiklik öznesi kodu: user veya role'");
    }
};
