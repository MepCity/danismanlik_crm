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
        Schema::table('interactions', static function (Blueprint $table): void {
            $table->string('direction')->nullable();
            $table->string('purpose')->nullable();
        });

        DB::statement("UPDATE interactions SET direction = 'outbound', purpose = CASE WHEN lead_id IS NOT NULL THEN 'marketing' ELSE 'service' END WHERE type = 'call'");
        DB::statement("ALTER TABLE interactions ADD CONSTRAINT interactions_call_context CHECK ((type = 'call' AND direction IN ('inbound', 'outbound') AND purpose IN ('marketing', 'service')) OR (type <> 'call' AND direction IS NULL AND purpose IS NULL))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE interactions DROP CONSTRAINT interactions_call_context');

        Schema::table('interactions', static function (Blueprint $table): void {
            $table->dropColumn(['direction', 'purpose']);
        });
    }
};
