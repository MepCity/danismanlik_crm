<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Audit\Actor;
use App\Support\Audit\ActorContext;
use App\Support\Audit\ActorSource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyActorContext
{
    public function __construct(private ActorContext $actorContext) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->getAuthIdentifier();

        return $this->actorContext->run(
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
