<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE TRIGGER interactions_audit AFTER INSERT OR UPDATE OR DELETE ON interactions FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]')");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS interactions_audit ON interactions');
    }
};
