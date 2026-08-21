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
        Schema::table('companies', static function (Blueprint $table): void {
            $table->foreignId('owner_user_id')
                ->nullable()
                ->after('id')
                ->index()
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('industry', 80)->default('other')->after('nace_code')->index();
        });

        DB::statement("COMMENT ON COLUMN companies.owner_user_id IS 'Firma rehberi kaydının sorumlusu; eski ve sistem kaynaklı kayıtlar için NULL olabilir'");
        DB::statement("COMMENT ON COLUMN companies.industry IS 'Sektörel segmentasyon ve izinli iletişim hedeflemesi için kontrollü sektör kodu'");
    }

    public function down(): void
    {
        Schema::table('companies', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn('industry');
        });
    }
};
