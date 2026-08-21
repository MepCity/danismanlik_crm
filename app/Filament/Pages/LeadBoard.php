<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Access\Services\PageAccess;
use App\Domain\Crm\Actions\TransitionLead;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Exceptions\StatusTransitionRejected;
use App\Domain\Deal\Models\Status;
use App\Domain\Deal\Models\Transition;
use App\Domain\Program\Models\ProgramVersion;
use App\Filament\Support\MarketingOperationsView;
use App\Models\User;
use App\Support\Authorization\ScopedQuery;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

final class LeadBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $slug = 'takip-panosu';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.lead-board';

    public ?int $transitionLeadId = null;

    public ?int $transitionTargetId = null;

    public ?string $nextCallAt = null;

    public ?int $ownerUserId = null;

    public string $lostReason = '';

    public ?int $programVersionId = null;

    public ?string $transitionError = null;

    public ?int $selectedLeadId = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(PageAccess::class)->allows($user, self::class) && Gate::allows('viewAny', Lead::class);
    }

    public static function getNavigationLabel(): string
    {
        return __('marketing.board.navigation');
    }

    public static function getNavigationGroup(): string
    {
        return __('panel.navigation.groups.marketing');
    }

    public function getTitle(): string
    {
        return __('marketing.board.title');
    }

    public function openLead(int $leadId): void
    {
        $this->selectedLeadId = $this->lead($leadId)->id;
    }

    public function closeLead(): void
    {
        $this->selectedLeadId = null;
    }

    public function moveLead(int $leadId, int $targetStatusId, TransitionLead $transitions): void
    {
        $lead = $this->lead($leadId);
        if ($lead->status_id === $targetStatusId) {
            return;
        }

        $transition = Transition::query()->where('from_status_id', $lead->status_id)
            ->where('to_status_id', $targetStatusId)->where('is_active', true)->first();
        if ($transition === null) {
            Notification::make()->title(__('marketing.board.invalid_drop'))->warning()->send();

            return;
        }

        $target = Status::query()->findOrFail($targetStatusId);
        if ($target->required_fields !== []) {
            $this->beginTransition($leadId, $targetStatusId);

            return;
        }

        try {
            $transitions->handle($lead->id, $targetStatusId, (int) Auth::id());
            Notification::make()->title(__('marketing.messages.transitioned'))->success()->send();
        } catch (StatusTransitionRejected $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function beginTransition(int $leadId, int $targetStatusId): void
    {
        $lead = $this->lead($leadId);
        abort_unless(Transition::query()->where('from_status_id', $lead->status_id)->where('to_status_id', $targetStatusId)->where('is_active', true)->exists(), 422);
        $this->transitionLeadId = $lead->id;
        $this->transitionTargetId = $targetStatusId;
        $this->ownerUserId = $lead->owner_user_id;
        $this->nextCallAt = $lead->next_call_at?->format('Y-m-d\TH:i');
        $this->lostReason = '';
        $this->programVersionId = $lead->interested_program_version_id;
        $this->transitionError = null;
    }

    public function saveTransition(TransitionLead $transitions): void
    {
        $this->validate(['transitionLeadId' => ['required', 'integer'], 'transitionTargetId' => ['required', 'integer']]);
        $lead = $this->lead((int) $this->transitionLeadId);
        Gate::authorize('update', $lead);

        try {
            $dealId = $transitions->handle(
                $lead->id,
                (int) $this->transitionTargetId,
                (int) Auth::id(),
                $this->nextCallAt,
                $this->ownerUserId,
                $this->lostReason ?: null,
                $this->programVersionId,
            );
            $this->resetTransition();
            Notification::make()->title($dealId === null ? __('marketing.messages.transitioned') : __('marketing.messages.converted'))->success()->send();
        } catch (StatusTransitionRejected $exception) {
            $this->transitionError = $exception->getMessage();
        }
    }

    public function cancelTransition(): void
    {
        $this->resetTransition();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $query = app(ScopedQuery::class)->apply(Lead::query(), $user, 'viewAny');
        $selectedTarget = $this->transitionTargetId === null ? null : Status::query()->find($this->transitionTargetId);

        return [
            'statuses' => Status::query()->where('type', 'lead')->where('is_active', true)->orderBy('sort_order')->get(),
            'leadsByStatus' => MarketingOperationsView::board($query),
            'selectedTarget' => $selectedTarget,
            'owners' => User::role(['Pazarlama', 'Şirket Yetkilisi'])->where('is_active', true)->orderBy('name')->get(),
            'programVersions' => ProgramVersion::query()->with('program')->where('is_active', true)->orderBy('id')->get(),
            'selectedLead' => $this->selectedLeadId === null ? null : $query->clone()->with(['company.contacts', 'owner', 'status', 'interestedProgramVersion.program', 'interactions'])->find($this->selectedLeadId),
        ];
    }

    private function lead(int $leadId): Lead
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);
        $lead = Lead::query()->findOrFail($leadId);
        abort_unless(app(ScopedQuery::class)->contains($user, $lead, 'view'), 403);

        return $lead;
    }

    private function resetTransition(): void
    {
        $this->reset('transitionLeadId', 'transitionTargetId', 'nextCallAt', 'ownerUserId', 'lostReason', 'programVersionId', 'transitionError');
    }
}
