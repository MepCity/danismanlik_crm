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
        Schema::table('deals', static function (Blueprint $table): void {
            $table->jsonb('workflow_snapshot')->nullable()->after('program_version_id');
        });

        DB::statement(<<<'SQL'
            UPDATE deals
            SET workflow_snapshot = program_versions.workflow_snapshot
            FROM program_versions
            WHERE program_versions.id = deals.program_version_id
              AND deals.workflow_snapshot IS NULL
            SQL);
        DB::statement("COMMENT ON COLUMN deals.workflow_snapshot IS 'Dosya açılışında hizmet döneminden kopyalanan değişmez bilgilendirici uygulama rehberi'");
    }

    public function down(): void
    {
        Schema::table('deals', static function (Blueprint $table): void {
            $table->dropColumn('workflow_snapshot');
        });
    }
};
