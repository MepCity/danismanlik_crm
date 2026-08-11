<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Closure;

final class ActorHolder
{
    private ?Actor $actor = null;

    public function current(): ?Actor
    {
        return $this->actor;
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function runWith(Actor $actor, Closure $callback): mixed
    {
        $previousActor = $this->actor;
        $this->actor = $actor;

        try {
            return $callback();
        } finally {
            $this->actor = $previousActor;
        }
    }
}
