<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Access\Enums\DataScope;
use App\Domain\Access\Services\EffectiveScopeResolver;
use App\Domain\Collaboration\Models\Activity;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Illuminate\Support\Collection;

final readonly class CollaborationReportGateway
{
    public function __construct(
        private ScopedQuery $scopes,
        private EffectiveScopeResolver $scopeResolver,
        private ActivityTranslator $activities,
    ) {}

    /** @return Collection<int, array{actor: string, sentence: string, occurred_at: non-falsy-string}> */
    public function recentActivities(User $user, int $limit): Collection
    {
        if ($this->scopeResolver->resolve($user) === DataScope::None
            || ! $user->hasAnyPermission(['audit.view_own', 'audit.view_team', 'audit.view_all'])) {
            return collect();
        }

        return $this->scopes->apply(Activity::query(), $user, 'audit.view')
            ->with('actor')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Activity $activity): array => [
                'actor' => $this->activities->actor($activity->actor?->name, $activity->source),
                'sentence' => $this->activities->sentence($activity->action, $activity->payload),
                'occurred_at' => $activity->created_at->format('d.m.Y H:i'),
            ]);
    }
}
