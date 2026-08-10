<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\TransactionBeginning;

final readonly class ApplyActorToTransaction
{
    public function __construct(private ActorHolder $actorHolder) {}

    public function handle(TransactionBeginning $event): void
    {
        $actor = $this->actorHolder->current();

        if ($actor === null) {
            return;
        }

        $this->setLocal($event->connection, 'app.source', $actor->source->value);
        $this->setLocalWhenPresent($event->connection, 'app.actor_id', $actor->id);
        $this->setLocalWhenPresent($event->connection, 'app.session_id', $actor->sessionId);
        $this->setLocalWhenPresent($event->connection, 'app.client_ip', $actor->clientIp);
    }

    private function setLocalWhenPresent(Connection $connection, string $setting, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->setLocal($connection, $setting, $value);
        }
    }

    private function setLocal(Connection $connection, string $setting, string $value): void
    {
        $connection->select('select set_config(?, ?, true)', [$setting, $value]);
    }
}
