<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE statuses
            ADD CONSTRAINT statuses_color_token
            CHECK (color IN ('neutral', 'info', 'waiting', 'success', 'danger'))
            SQL);

        DB::statement("COMMENT ON COLUMN statuses.color IS 'WP-14 tasarım tokenı: neutral, info, waiting, success veya danger; doğrudan renk/hex değeri değildir'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE statuses DROP CONSTRAINT IF EXISTS statuses_color_token');
        DB::statement('COMMENT ON COLUMN statuses.color IS NULL');
    }
};
