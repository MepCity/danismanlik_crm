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
        Schema::table('users', static function (Blueprint $table): void {
            $table->timestampTz('app_authentication_enabled_at')->nullable();
        });

        DB::table('users')
            ->whereNotNull('app_authentication_secret')
            ->update(['app_authentication_enabled_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn('app_authentication_enabled_at');
        });
    }
};
