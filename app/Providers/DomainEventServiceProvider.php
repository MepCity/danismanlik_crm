<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Events\DomainEvent;
use App\Support\Outbox\OutboxWriter;
use App\Support\Outbox\PendingOutboxWriter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class DomainEventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OutboxWriter::class, PendingOutboxWriter::class);
    }

    public function boot(): void
    {
        Event::listen('*', function (string $eventName, array $payload): void {
            $event = $payload[0] ?? null;

            if ($event instanceof DomainEvent) {
                $this->app->make(OutboxWriter::class)->write($event);
            }
        });
    }
}
