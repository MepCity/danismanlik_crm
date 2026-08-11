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
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->enum('data_scope', ['own', 'team', 'all', 'none'])->nullable()->index();
        });

        DB::statement("COMMENT ON TABLE users IS 'Kişiye özel kimlik hesapları; hesaplar silinmez, pasifleştirilir'");
        DB::statement("COMMENT ON COLUMN users.data_scope IS 'Rol varsayılanını ezen satır görünürlüğü: own, team, all veya none'");
        DB::statement("COMMENT ON COLUMN users.deactivated_at IS 'Hesabın pasifleştirildiği zaman; aktif hesaplarda NULL'");
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropIndex(['data_scope']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['is_active', 'deactivated_at', 'last_login_at', 'data_scope']);
        });
    }
};
