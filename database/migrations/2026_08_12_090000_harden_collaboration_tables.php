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
            $table->timestampTz('reminder_sent_at')->nullable()->after('remind_at');
        });

        DB::statement('CREATE INDEX tasks_due_reminders ON tasks (remind_at, id) WHERE completed_at IS NULL AND reminder_sent_at IS NULL AND remind_at IS NOT NULL');
        DB::statement("CREATE INDEX notifications_pending_email ON notifications (created_at, id) WHERE channel = 'email' AND delivery_status = 'pending'");

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_collaboration_delete()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION '% records cannot be deleted', TG_TABLE_NAME;
            END;
            $$;

            CREATE TRIGGER comments_no_delete
            BEFORE DELETE ON comments
            FOR EACH ROW EXECUTE FUNCTION prevent_collaboration_delete();

            CREATE TRIGGER tasks_no_delete
            BEFORE DELETE ON tasks
            FOR EACH ROW EXECUTE FUNCTION prevent_collaboration_delete();

            CREATE TRIGGER tasks_audit
            AFTER INSERT OR UPDATE ON tasks
            FOR EACH ROW EXECUTE FUNCTION write_audit_log('[]');
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS tasks_audit ON tasks');
        DB::statement('DROP TRIGGER IF EXISTS tasks_no_delete ON tasks');
        DB::statement('DROP TRIGGER IF EXISTS comments_no_delete ON comments');
        DB::statement('DROP FUNCTION IF EXISTS prevent_collaboration_delete()');
        DB::statement('DROP INDEX IF EXISTS notifications_pending_email');
        DB::statement('DROP INDEX IF EXISTS tasks_due_reminders');

        Schema::table('tasks', static function (Blueprint $table): void {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
