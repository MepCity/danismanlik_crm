<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Filament\Support\DealOperationsView;
use App\Support\Authorization\ScopedQuery;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class DealBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $slug = 'dosyalar';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.deal-board';

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Deal::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('operations.board.navigation');
    }

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.operations');
    }

    public function getTitle(): string
    {
        return __('operations.board.title');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $query = app(ScopedQuery::class)->apply(Deal::query(), $user, 'viewAny');

        return [
            'statuses' => Status::query()->where('type', 'deal')->where('is_active', true)->orderBy('sort_order')->get(),
            'dealsByStatus' => DealOperationsView::board($query),
            'delayedStatusDays' => (int) config('operations.delayed_status_days'),
        ];
    }
}
