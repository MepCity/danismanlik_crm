<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Deal\Actions\AssignDeal;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Transition;
use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

final class PendingAssignments extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $slug = 'atama-bekleyen-isler';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.pending-assignments';

    /** @var array<int, int|string|null> */
    public array $projectManagerIds = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('deal.assign') === true;
    }

    public static function getNavigationLabel(): string
    {
        return __('operations.assignment.navigation');
    }

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.operations');
    }

    public function getTitle(): string
    {
        return __('operations.assignment.title');
    }

    public function assign(int $dealId, AssignDeal $assignments): void
    {
        $this->validate(["projectManagerIds.{$dealId}" => ['required', 'integer']]);
        $deal = Deal::query()->whereNull('pm_user_id')->findOrFail($dealId);
        $transition = Transition::query()
            ->where('from_status_id', $deal->status_id)
            ->where('required_permission', 'deal.assign')
            ->where('is_active', true)
            ->sole();
        $assignments->handle($deal->id, (int) $this->projectManagerIds[$dealId], $transition->to_status_id, (int) Auth::id());
        unset($this->projectManagerIds[$dealId]);
        Notification::make()->title(__('operations.messages.assigned'))->success()->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $deals = Deal::query()->whereNull('pm_user_id')->with([
            'company', 'programVersion.program', 'status', 'openedBy',
            'originatingLead.primaryContact', 'originatingLead.owner', 'originatingLead.statusHistory.status',
            'originatingLead.interactions' => fn ($query) => $query->latest('occurred_at'),
        ])->oldest()->get();
        $managers = User::role('Proje Yöneticisi')->where('is_active', true)
            ->withCount(['managedDeals as active_deals_count' => fn ($query) => $query->whereHas('status', fn ($status) => $status->where('is_terminal', false))])
            ->orderBy('name')->get();

        return compact('deals', 'managers');
    }
}
