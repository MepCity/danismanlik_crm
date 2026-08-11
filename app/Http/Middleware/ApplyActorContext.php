<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Audit\Actor;
use App\Support\Audit\ActorHolder;
use App\Support\Audit\ActorSource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyActorContext
{
    public function __construct(private ActorHolder $actorHolder) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->getAuthIdentifier();

        return $this->actorHolder->runWith(
            new Actor(
                source: ActorSource::User,
                id: $userId !== null ? (string) $userId : null,
                sessionId: $request->hasSession() ? $request->session()->getId() : null,
                clientIp: $request->ip(),
            ),
            fn (): Response => $next($request),
        );
    }
}
