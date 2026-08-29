<?php

declare(strict_types=1);

namespace App\Filament\Resources\ServiceWorkflows\Pages;

use App\Domain\Program\Models\ServiceWorkflow;
use App\Filament\Concerns\HasConsistentListChrome;
use App\Filament\Resources\ServiceWorkflows\ServiceWorkflowResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

final class ListServiceWorkflows extends ListRecords
{
    use HasConsistentListChrome;

    protected static string $resource = ServiceWorkflowResource::class;

    protected string $view = 'filament.resources.service-workflows.pages.list-service-workflows';

    /** Name typed into the inline create control. Kept in component state only. */
    public string $newWorkflowName = '';

    #[Url(as: 'ara', keep: false)]
    public string $workflowSearch = '';

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * Cubicl starts an empty workflow immediately; Bizlife keeps the domain rule
     * that a workflow needs at least one step, so the typed name is carried into
     * the create workspace instead of being persisted here.
     */
    public function startWorkflow(): void
    {
        $this->validate(
            ['newWorkflowName' => ['required', 'string', 'max:255']],
            [
                'newWorkflowName.required' => __('management.workflow_setup.validation.name_required'),
                'newWorkflowName.max' => __('management.workflow_setup.validation.name_max'),
            ],
        );

        $name = trim($this->newWorkflowName);

        if ($name === '') {
            $this->addError('newWorkflowName', __('management.workflow_setup.validation.name_required'));

            return;
        }

        $this->redirect(
            ServiceWorkflowResource::getUrl('create', ['name' => $name]),
            navigate: true,
        );
    }

    /** @return Collection<int, ServiceWorkflow> */
    public function getWorkflowCards(): Collection
    {
        $search = trim($this->workflowSearch);

        return ServiceWorkflowResource::getEloquentQuery()
            ->when($search !== '', fn (Builder $query): Builder => $query->where('name', 'ilike', '%'.$search.'%'))
            ->withCount(['programVersions'])
            ->with(['steps' => fn ($steps) => $steps->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('name')
            ->get();
    }
}
