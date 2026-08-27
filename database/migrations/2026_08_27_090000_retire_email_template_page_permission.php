<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'page.email_templates')
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'page.email_templates')
            ->update(['is_active' => true, 'updated_at' => now()]);
    }
};
