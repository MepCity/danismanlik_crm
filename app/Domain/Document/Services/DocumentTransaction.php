<?php

declare(strict_types=1);

namespace App\Domain\Document\Services;

use App\Support\Audit\Actor;
use App\Support\Audit\ActorHolder;
use App\Support\Audit\ActorSource;
use Closure;
use Illuminate\Support\Facades\DB;

final readonly class DocumentTransaction
{
    public function __construct(private ActorHolder $actors) {}

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(ActorSource $source, ?int $actorId, Closure $callback): mixed
    {
        $previous = $this->actors->current();
        $actor = new Actor($source, $actorId === null ? null : (string) $actorId);

        return $this->actors->runWith(
            $actor,
            fn (): mixed => DB::transaction(function () use ($callback, $previous): mixed {
                try {
                    return $callback();
                } finally {
                    $this->restoreDatabaseActor($previous);
                }
            }),
        );
    }

    private function restoreDatabaseActor(?Actor $actor): void
    {
        $values = $actor === null
            ? [
                'app.source' => 'system',
                'app.actor_id' => '',
                'app.session_id' => '',
                'app.client_ip' => '',
            ]
            : [
                'app.source' => $actor->source->value,
                'app.actor_id' => $actor->id ?? '',
                'app.session_id' => $actor->sessionId ?? '',
                'app.client_ip' => $actor->clientIp ?? '',
            ];

        foreach ($values as $setting => $value) {
            DB::select('select set_config(?, ?, true)', [$setting, $value]);
        }
    }
}
