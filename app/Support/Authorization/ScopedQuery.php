<?php

declare(strict_types=1);

namespace App\Support\Authorization;

use App\Domain\Access\Enums\DataScope;
use App\Domain\Access\Models\BreakGlassGrant;
use App\Domain\Access\Services\ActiveBreakGlass;
use App\Domain\Access\Services\EffectiveScopeResolver;
use App\Domain\Collaboration\Models\Activity;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\Task;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Interaction;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Domain\Document\Models\DealDocument;
use App\Domain\Document\Models\File;
use App\Domain\Program\Models\DocTemplate;
use App\Domain\Program\Models\Program;
use App\Domain\Program\Models\ProgramVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;

final readonly class ScopedQuery
{
    public function __construct(
        private EffectiveScopeResolver $scopes,
        private ActiveBreakGlass $breakGlass,
    ) {}

    /** @template TModel of Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, User $user, string $ability = 'viewAny'): Builder
    {
        $model = $query->getModel();

        $configurationPermission = $this->configurationPermission($model);
        if ($configurationPermission !== null) {
            return $user->is_active && $user->can($configurationPermission)
                ? $query
                : $query->whereRaw('1 = 0');
        }

        if ($this->breakGlass->use($user, $ability, $model::class) !== null) {
            return $query;
        }

        $scope = $this->scopes->resolve($user);

        if ($scope === DataScope::All) {
            return $query;
        }

        if ($scope === DataScope::None) {
            return $query->whereRaw('1 = 0');
        }

        $userIds = $this->visibleUserIds($user, $scope);

        return match ($model::class) {
            Company::class => $this->companies($query, $userIds),
            Contact::class => $this->contacts($query, $userIds),
            Lead::class => $query->whereIn('leads.owner_user_id', $userIds),
            Interaction::class => $query->whereIn('interactions.user_id', $userIds),
            Deal::class => $this->deals($query, $userIds),
            DealDocument::class => $this->dealDocuments($query, $userIds),
            File::class => $this->files($query, $userIds),
            Comment::class => $this->comments($query, $userIds),
            Activity::class => $this->activities($query, $userIds),
            Task::class => $this->tasks($query, $userIds),
            default => throw new InvalidArgumentException('Unsupported scoped model: '.$model::class),
        };
    }

    public function contains(User $user, Model $model, string $ability): bool
    {
        return $this->apply($model->newQuery(), $user, $ability)
            ->whereKey($model->getKey())
            ->exists();
    }

    public function allowsAny(User $user, string $ability): bool
    {
        if ($this->breakGlass->use($user, $ability) !== null) {
            return true;
        }

        return $this->scopes->resolve($user) !== DataScope::None;
    }

    private function configurationPermission(Model $model): ?string
    {
        return match ($model::class) {
            Program::class, ProgramVersion::class, DocTemplate::class => 'program.view',
            Status::class, Transition::class => 'system.settings',
            User::class => 'system.users',
            Role::class => 'system.roles',
            BreakGlassGrant::class => 'access.break_glass.grant',
            default => null,
        };
    }

    private function visibleUserIds(User $user, DataScope $scope): QueryBuilder
    {
        if ($scope === DataScope::Own) {
            return DB::query()->selectRaw('?::bigint AS user_id', [$user->id]);
        }

        $teamIds = DB::table('team_members')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->where('team_members.user_id', $user->id)
            ->where('teams.is_active', true)
            ->select('team_members.team_id');

        return DB::table('team_members')
            ->select('team_members.user_id')
            ->whereIn('team_members.team_id', $teamIds)
            ->union(DB::query()->selectRaw('?::bigint AS user_id', [$user->id]));
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function companies(Builder $query, QueryBuilder $userIds): Builder
    {
        return $query->where(function (Builder $query) use ($userIds): void {
            $query->whereHas('leads', static fn (Builder $lead): Builder => $lead->whereIn('owner_user_id', $userIds))
                ->orWhereHas('deals', static function (Builder $deal) use ($userIds): void {
                    $deal->whereIn('opened_by_user_id', $userIds)->orWhereIn('pm_user_id', $userIds);
                });
        });
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function contacts(Builder $query, QueryBuilder $userIds): Builder
    {
        return $query->whereHas('company', fn (Builder $company): Builder => $this->companies($company, $userIds));
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function deals(Builder $query, QueryBuilder $userIds): Builder
    {
        return $query->where(function (Builder $query) use ($userIds): void {
            $query->whereIn('deals.opened_by_user_id', $userIds)
                ->orWhereIn('deals.pm_user_id', $userIds);
        });
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function dealDocuments(Builder $query, QueryBuilder $userIds): Builder
    {
        return $query->whereHas('deal', fn (Builder $deal): Builder => $this->deals($deal, $userIds));
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function files(Builder $query, QueryBuilder $userIds): Builder
    {
        return $query->whereHas(
            'dealDocument.deal',
            fn (Builder $deal): Builder => $this->deals($deal, $userIds),
        );
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function comments(Builder $query, QueryBuilder $userIds): Builder
    {
        return $query->where(function (Builder $query) use ($userIds): void {
            $query->whereHas('company', fn (Builder $company): Builder => $this->companies($company, $userIds))
                ->orWhereHas('lead', static fn (Builder $lead): Builder => $lead->whereIn('owner_user_id', $userIds))
                ->orWhereHas('deal', function (Builder $deal) use ($userIds): Builder {
                    return $this->deals($deal, $userIds);
                })
                ->orWhereHas('dealDocument.deal', function (Builder $deal) use ($userIds): Builder {
                    return $this->deals($deal, $userIds);
                });
        });
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function activities(Builder $query, QueryBuilder $userIds): Builder
    {
        return $query->where(function (Builder $query) use ($userIds): void {
            $query->whereIn('activities.actor_id', $userIds)
                ->orWhereHas('company', fn (Builder $company): Builder => $this->companies($company, $userIds))
                ->orWhereHas('lead', static fn (Builder $lead): Builder => $lead->whereIn('owner_user_id', $userIds))
                ->orWhereHas('deal', function (Builder $deal) use ($userIds): Builder {
                    return $this->deals($deal, $userIds);
                })
                ->orWhereHas('dealDocument.deal', function (Builder $deal) use ($userIds): Builder {
                    return $this->deals($deal, $userIds);
                });
        });
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function tasks(Builder $query, QueryBuilder $userIds): Builder
    {
        return $query->where(function (Builder $query) use ($userIds): void {
            $query->whereIn('tasks.assigned_to', $userIds)
                ->orWhereIn('tasks.created_by', $userIds)
                ->orWhereHas('company', fn (Builder $company): Builder => $this->companies($company, $userIds))
                ->orWhereHas('lead', static fn (Builder $lead): Builder => $lead->whereIn('owner_user_id', $userIds))
                ->orWhereHas('deal', function (Builder $deal) use ($userIds): Builder {
                    return $this->deals($deal, $userIds);
                })
                ->orWhereHas('dealDocument.deal', function (Builder $deal) use ($userIds): Builder {
                    return $this->deals($deal, $userIds);
                });
        });
    }
}
