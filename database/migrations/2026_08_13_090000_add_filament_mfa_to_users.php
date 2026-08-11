<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CURRENT_EXCLUSIONS = '["password","remember_token","api_token","two_factor_secret","two_factor_recovery_codes","signed_url_secret","e_signature_password","app_authentication_secret","app_authentication_recovery_codes"]';

    private const PREVIOUS_EXCLUSIONS = '["password","remember_token","api_token","two_factor_secret","two_factor_recovery_codes","signed_url_secret","e_signature_password"]';

    public function up(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });

        $this->replaceUsersAuditTrigger(self::CURRENT_EXCLUSIONS);
    }

    public function down(): void
    {
        $this->replaceUsersAuditTrigger(self::PREVIOUS_EXCLUSIONS);

        Schema::table('users', static function (Blueprint $table): void {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }

    private function replaceUsersAuditTrigger(string $exclusions): void
    {
        DB::statement('DROP TRIGGER IF EXISTS users_audit ON users');
        DB::statement(sprintf(
            "CREATE TRIGGER users_audit AFTER INSERT OR UPDATE OR DELETE ON users FOR EACH ROW EXECUTE FUNCTION write_audit_log('%s')",
            $exclusions,
        ));
    }
};
