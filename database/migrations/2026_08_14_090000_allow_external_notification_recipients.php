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
        Schema::table('notifications', static function (Blueprint $table): void {
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->foreignId('user_id')->nullable()->change();
        });

        DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_exactly_one_recipient CHECK ((user_id IS NOT NULL)::int + (recipient_email IS NOT NULL)::int = 1)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications DROP CONSTRAINT notifications_exactly_one_recipient');

        Schema::table('notifications', static function (Blueprint $table): void {
            $table->dropColumn(['recipient_email', 'recipient_name']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
