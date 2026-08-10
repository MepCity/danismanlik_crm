<?php

declare(strict_types=1);

use App\Support\Queue\AutomationJob;
use Illuminate\Support\Facades\DB;

it('applies the automation actor source to queue jobs', function (): void {
    $job = new class extends AutomationJob
    {
        public ?string $source = null;

        public ?int $transactionLevel = null;

        protected function execute(): void
        {
            $this->transactionLevel = DB::transactionLevel();
            $this->source = DB::transaction(
                fn (): string => (string) DB::scalar("select current_setting('app.source', true)"),
            );
        }
    };

    app()->call([$job, 'handle']);

    expect($job->transactionLevel)->toBe(0);
    expect($job->source)->toBe('automation');
    expect((string) DB::scalar("select current_setting('app.source', true)"))->toBeEmpty();
});
