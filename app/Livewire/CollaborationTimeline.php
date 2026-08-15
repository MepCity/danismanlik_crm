<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\DTOs\TimelineItem;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Collaboration\Services\TimelineQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class CollaborationTimeline extends Component
{
    public string $subjectType;

    public int $subjectId;

    public string $filter = 'all';

    public int $page = 1;

    public int $perPage = 25;

    public bool $embedded = false;

    public function mount(string $subjectType, int $subjectId, string $filter = 'all', bool $embedded = false): void
    {
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->setFilter($filter);
        $this->embedded = $embedded;
        $this->timeline();
    }

    public function setFilter(string $filter): void
    {
        abort_unless(in_array($filter, ['all', 'activity', 'status', 'document', 'comment'], true), 422);
        $this->filter = $filter;
        $this->page = 1;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function render(): View
    {
        return view('livewire.collaboration-timeline', ['timeline' => $this->timeline()]);
    }

    /** @return LengthAwarePaginator<int, TimelineItem> */
    private function timeline(): LengthAwarePaginator
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        return app(TimelineQuery::class)->paginate(
            $user,
            new SubjectReference(CollaborationSubjectType::from($this->subjectType), $this->subjectId),
            $this->filter === 'all' ? null : $this->filter,
            $this->perPage,
            $this->page,
        );
    }
}
