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
        Schema::table('tasks', static function (Blueprint $table): void {
            $table->timestamp('due_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('tasks')->whereNull('due_at')->update(['due_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);

        Schema::table('tasks', static function (Blueprint $table): void {
            $table->timestamp('due_at')->nullable(false)->change();
        });
    }
};
