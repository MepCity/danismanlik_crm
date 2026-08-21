<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE companies ALTER COLUMN city DROP NOT NULL');
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_industry_valid CHECK (industry IN ('food', 'agriculture', 'manufacturing', 'metal', 'machinery', 'electrical_electronics', 'automotive', 'textile', 'plastic', 'construction', 'energy', 'technology', 'information_technology', 'software', 'telecommunications', 'healthcare', 'pharmaceuticals', 'education', 'finance', 'insurance', 'logistics', 'tourism', 'retail', 'services', 'consulting', 'media', 'mining', 'chemicals', 'packaging', 'furniture', 'other'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE companies DROP CONSTRAINT companies_industry_valid');
        DB::statement('ALTER TABLE companies ALTER COLUMN city SET NOT NULL');
    }
};
