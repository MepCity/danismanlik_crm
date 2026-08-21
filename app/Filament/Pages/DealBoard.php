<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Access\Services\PageAccess;
use App\Domain\Deal\Actions\AssignDeal;
use App\Domain\Deal\Exceptions\StatusTransitionRejected;
use App\Domain\Deal\Models\Deal;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Domain\Deal\Services\StatusMachineContract;
use App\Filament\Support\DealOperationsView;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use App\Support\Workflow\StatusTransition;
use App\Support\Workflow\SubjectType;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;

final class DealBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $slug = 'dosyalar';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.deal-board';

    #[Url(as: 'filter')]
    public string $filter = '';

    public ?int $selectedDealId = null;

    public ?int $transitionDealId = null;

    public ?int $transitionTargetId = null;

    public ?int $projectManagerId = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(PageAccess::class)->allows($user, self::class) && Gate::allows('viewAny', Deal::class);
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

    public function openDeal(int $dealId): void
    {
        $this->selectedDealId = $this->deal($dealId)->id;
    }

    public function closeDeal(): void
    {
        $this->selectedDealId = null;
    }

    public function moveDeal(int $dealId, int $targetStatusId, StatusMachineContract $machine): void
    {
        $deal = $this->deal($dealId);
        if ($deal->status_id === $targetStatusId) {
            return;
        }

        $transition = Transition::query()->where('from_status_id', $deal->status_id)
            ->where('to_status_id', $targetStatusId)->where('is_active', true)->first();
        if ($transition === null) {
            Notification::make()->title(__('operations.board.invalid_drop'))->warning()->send();

            return;
        }

        $target = Status::query()->findOrFail($targetStatusId);
        if (in_array('project_manager_id', $target->required_fields, true) && $deal->pm_user_id === null) {
            $this->transitionDealId = $deal->id;
            $this->transitionTargetId = $target->id;

            return;
        }

        try {
            $machine->transition(new StatusTransition(SubjectType::Deal, $deal->id, $targetStatusId, (int) Auth::id()));
            Notification::make()->title(__('operations.messages.transitioned'))->success()->send();
        } catch (StatusTransitionRejected $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function assignAndMove(AssignDeal $assignments): void
    {
        $this->validate([
            'transitionDealId' => ['required', 'integer'],
            'transitionTargetId' => ['required', 'integer'],
            'projectManagerId' => ['required', 'integer'],
        ]);
        $assignments->handle((int) $this->transitionDealId, (int) $this->projectManagerId, (int) $this->transitionTargetId, (int) Auth::id());
        $this->reset('transitionDealId', 'transitionTargetId', 'projectManagerId');
        Notification::make()->title(__('operations.messages.assigned'))->success()->send();
    }

    public function cancelMove(): void
    {
        $this->reset('transitionDealId', 'transitionTargetId', 'projectManagerId');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        abort_unless(in_array($this->filter, ['', 'new_assignments', 'customer_response'], true), 404);

        $query = app(ScopedQuery::class)->apply(Deal::query(), $user, 'viewAny');

        if ($this->filter !== '') {
            $query = DealOperationsView::dashboardFilter($query, $this->filter, $user->id);
        }

        return [
            'statuses' => Status::query()->where('type', 'deal')->where('is_active', true)->orderBy('sort_order')->get(),
            'dealsByStatus' => DealOperationsView::board($query),
            'delayedStatusDays' => (int) config('operations.delayed_status_days'),
            'filterLabel' => $this->filter === '' ? null : __("reporting.dashboard.filters.{$this->filter}"),
            'selectedDeal' => $this->selectedDealId === null ? null : $query->clone()->with(['company.contacts', 'programVersion.program', 'projectManager', 'status', 'interactions'])->find($this->selectedDealId),
            'projectManagers' => User::role('Proje Yöneticisi')->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function deal(int $dealId): Deal
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $deal = Deal::query()->findOrFail($dealId);
        abort_unless(app(ScopedQuery::class)->contains($user, $deal, 'view'), 403);

        return $deal;
    }
}
