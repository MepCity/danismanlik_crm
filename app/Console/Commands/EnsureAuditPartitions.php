<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EnsureAuditPartitions extends Command
{
    protected $signature = 'audit:ensure-partitions {--months=3}';

    protected function configure(): void
    {
        parent::configure();

        $this->setDescription((string) __('audit.commands.ensure_partitions.description'));
    }

    public function handle(): int
    {
        if (! Schema::hasTable('audit_log')) {
            $this->components->error((string) __('audit.commands.ensure_partitions.missing_table'));

            return self::FAILURE;
        }

        $months = filter_var($this->option('months'), FILTER_VALIDATE_INT);

        if (! is_int($months) || $months < 0 || $months > 24) {
            $this->components->error((string) __('audit.commands.ensure_partitions.invalid_months'));

            return self::INVALID;
        }

        for ($offset = 0; $offset <= $months; $offset++) {
            $start = now('UTC')->startOfMonth()->addMonths($offset);
            $end = $start->copy()->addMonth();
            $name = 'audit_log_'.$start->format('Ym');

            DB::statement(sprintf(
                "CREATE TABLE IF NOT EXISTS %s PARTITION OF audit_log FOR VALUES FROM ('%s') TO ('%s')",
                $name,
                $start->toIso8601String(),
                $end->toIso8601String(),
            ));
        }

        $this->components->info((string) __('audit.commands.ensure_partitions.complete', [
            'count' => $months + 1,
        ]));

        return self::SUCCESS;
    }
}
