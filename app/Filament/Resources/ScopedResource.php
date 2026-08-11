<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use Closure;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @template TModel of \Illuminate\Database\Eloquent\Model */
abstract class ScopedResource extends Resource
{
    /** @return Builder<TModel> */
    final public static function getEloquentQuery(): Builder
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            throw new LogicException('Filament Resource sorgusu kimliği doğrulanmış bir kullanıcı gerektirir.');
        }

        /** @var Builder<TModel> $query */
        $query = parent::getEloquentQuery();

        return app(ScopedQuery::class)->apply($query, $user);
    }

    final public static function resolveRecordRouteBinding(int|string $key, ?Closure $modifyQuery = null): ?Model
    {
        $model = app(static::getModel());
        $query = $model->newQuery();

        if ($modifyQuery !== null) {
            $query = $modifyQuery($query) ?? $query;
        }

        $record = $model->resolveRouteBindingQuery($query, $key, static::getRecordRouteKeyName())->first();

        if (! $record instanceof Model) {
            return null;
        }

        $user = Filament::auth()->user();

        abort_unless(
            $user instanceof User && app(ScopedQuery::class)->contains($user, $record, 'view'),
            403,
        );

        return $record;
    }
}
