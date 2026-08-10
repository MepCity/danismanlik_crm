<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Closure;
use Illuminate\Database\Connection;

final readonly class ActorContext
{
    public function __construct(private Connection $connection) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(Actor $actor, Closure $callback): mixed
    {
        return $this->connection->transaction(function () use ($actor, $callback): mixed {
            $this->setLocal('app.source', $actor->source->value);
            $this->setLocalWhenPresent('app.actor_id', $actor->id);
            $this->setLocalWhenPresent('app.session_id', $actor->sessionId);
            $this->setLocalWhenPresent('app.client_ip', $actor->clientIp);

            return $callback();
        });
    }

    private function setLocalWhenPresent(string $setting, ?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->setLocal($setting, $value);
        }
    }

    private function setLocal(string $setting, string $value): void
    {
        $this->connection->select("select set_config('{$setting}', ?, true)", [$value]);
    }
}
