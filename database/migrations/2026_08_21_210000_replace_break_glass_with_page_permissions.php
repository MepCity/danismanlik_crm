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
        Schema::dropIfExists('break_glass_grants');

        $now = now();
        $permissions = [
            'page.access_managed', 'page.dashboard', 'page.companies', 'page.customers',
            'page.today_calls', 'page.opportunities', 'page.pending_assignments', 'page.deals',
            'page.reports', 'page.service_workflows', 'page.programs', 'page.document_templates',
            'page.workflow_settings', 'page.access_management',
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['is_active' => true, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        DB::table('permissions')->where('name', 'access.break_glass.grant')->update(['is_active' => false, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::create('break_glass_grants', static function (Blueprint $table): void {
            $table->bigInteger('id')->generatedAs()->primary();
            $table->foreignId('user_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('granted_by')->index()->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE break_glass_grants ADD CONSTRAINT break_glass_grants_reason_not_blank CHECK (length(btrim(reason)) > 0)');
        DB::statement('ALTER TABLE break_glass_grants ADD CONSTRAINT break_glass_grants_expiry_after_creation CHECK (expires_at > created_at)');
        DB::statement("COMMENT ON TABLE break_glass_grants IS 'Sistem yöneticisinin gerekçeli ve süreli acil iş verisi erişimi'");

        DB::table('permissions')->whereIn('name', [
            'page.access_managed', 'page.dashboard', 'page.companies', 'page.customers',
            'page.today_calls', 'page.opportunities', 'page.pending_assignments', 'page.deals',
            'page.reports', 'page.service_workflows', 'page.programs', 'page.document_templates',
            'page.workflow_settings', 'page.access_management',
        ])->update(['is_active' => false, 'updated_at' => now()]);
        DB::table('permissions')->where('name', 'access.break_glass.grant')->update(['is_active' => true, 'updated_at' => now()]);
    }
};
