<?php

declare(strict_types=1);

use App\Domain\Crm\Events\LeadConverted;
use App\Support\Events\DomainEvent;
use App\Support\Outbox\OutboxWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('writes a dispatched domain event to the outbox in the same transaction', function (): void {
    $writer = new class implements OutboxWriter
    {
        public ?DomainEvent $event = null;

        public int $transactionLevel = 0;

        public function write(DomainEvent $event): void
        {
            $this->event = $event;
            $this->transactionLevel = DB::transactionLevel();
        }
    };

    app()->instance(OutboxWriter::class, $writer);

    $event = new LeadConverted(leadId: 'lead-1', dealId: 'deal-1', actorId: 'user-1');

    DB::transaction(fn () => Event::dispatch($event));

    expect($writer->event)->toBe($event)
        ->and($writer->transactionLevel)->toBeGreaterThan(0);
});
