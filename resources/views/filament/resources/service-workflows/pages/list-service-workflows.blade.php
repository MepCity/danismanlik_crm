@php
    $workflows = $this->getWorkflowCards();
    $stepTypeIcons = [
        'action' => 'heroicon-o-play',
        'waiting' => 'heroicon-o-clock',
        'decision' => 'heroicon-o-check-badge',
    ];
    $stepTypeLabels = __('management.workflow_step_types');
@endphp

<x-filament-panels::page>
    <div class="cubicl-workflow" data-testid="cubicl-workflow-page">
        <section
            class="cubicl-workflow__intro"
            x-data="{ helpOpen: false }"
            data-testid="cubicl-workflow-intro"
        >
            <header class="cubicl-workflow__intro-header">
                <div class="cubicl-workflow__intro-title-group">
                    <h1>{{ __('management.workflow_setup.intro.title') }}</h1>
                    <button
                        type="button"
                        class="cubicl-workflow__help-button"
                        @click="helpOpen = ! helpOpen"
                        :aria-expanded="helpOpen.toString()"
                        aria-controls="cubicl-workflow-help"
                    >
                        <x-filament::icon icon="heroicon-o-book-open" />
                        <span>{{ __('management.workflow_setup.intro.help_action') }}</span>
                    </button>
                </div>

                @if (\App\Filament\Resources\Users\UserResource::canViewAny())
                    <a
                        href="{{ \App\Filament\Resources\Users\UserResource::getUrl() }}"
                        class="cubicl-workflow__permission-link"
                        wire:navigate
                        data-testid="workflow-permissions-link"
                    >
                        <x-filament::icon icon="heroicon-o-shield-check" />
                        <span>{{ __('management.workflow_setup.intro.permissions_action') }}</span>
                    </a>
                @else
                    <span class="cubicl-workflow__permission-pill" data-testid="workflow-permissions-badge">
                        <x-filament::icon icon="heroicon-o-shield-check" />
                        <span>{{ __('management.workflow_setup.intro.permission_badge') }}</span>
                    </span>
                @endif
            </header>

            <p>{{ __('management.workflow_setup.intro.summary') }}</p>
            <p>{{ __('management.workflow_setup.intro.scope') }}</p>

            <div
                id="cubicl-workflow-help"
                class="cubicl-workflow__help"
                x-cloak
                x-show="helpOpen"
                x-transition.opacity.duration.150ms
            >
                <strong>{{ __('management.workflow_setup.intro.help_title') }}</strong>
                <ol>
                    @foreach (__('management.workflow_setup.intro.help_steps') as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="cubicl-workflow__list" aria-labelledby="cubicl-workflow-list-title">
            <div class="cubicl-workflow__list-header">
                <h2 id="cubicl-workflow-list-title">{{ __('management.models.service_workflow.plural') }}</h2>

                <label class="cubicl-workflow__search" data-testid="cubicl-workflow-search">
                    <span class="fi-sr-only">{{ __('management.workflow_setup.search.label') }}</span>
                    <x-filament::icon icon="heroicon-o-magnifying-glass" aria-hidden="true" />
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="workflowSearch"
                        placeholder="{{ __('management.workflow_setup.search.placeholder') }}"
                        aria-label="{{ __('management.workflow_setup.search.label') }}"
                    />
                </label>
            </div>

            <form
                class="cubicl-workflow__inline-create"
                wire:submit.prevent="startWorkflow"
                data-testid="cubicl-workflow-inline-create"
            >
                <label class="fi-sr-only" for="cubicl-workflow-new-name">
                    {{ __('management.workflow_setup.inline.label') }}
                </label>
                <div class="cubicl-workflow__inline-field">
                    <input
                        id="cubicl-workflow-new-name"
                        type="text"
                        wire:model="newWorkflowName"
                        placeholder="{{ __('management.workflow_setup.inline.placeholder') }}"
                        maxlength="255"
                        autocomplete="off"
                        aria-describedby="cubicl-workflow-new-hint"
                        @error('newWorkflowName') aria-invalid="true" @enderror
                    />
                    <button type="submit" class="cubicl-workflow__inline-submit" data-testid="cubicl-workflow-inline-submit">
                        <span>{{ __('management.workflow_setup.inline.submit') }}</span>
                        <x-filament::icon icon="heroicon-o-plus" aria-hidden="true" />
                    </button>
                </div>
                {{-- Kept for assistive technology only: the reference row has no visible helper line. --}}
                <p id="cubicl-workflow-new-hint" class="cubicl-workflow__inline-hint fi-sr-only">
                    {{ __('management.workflow_setup.inline.hint') }}
                </p>
                @error('newWorkflowName')
                    <p class="cubicl-workflow__inline-error" data-testid="cubicl-workflow-inline-error" role="alert">
                        {{ $message }}
                    </p>
                @enderror
            </form>

            <div class="cubicl-workflow__cards" data-testid="cubicl-workflow-cards">
                @forelse ($workflows as $workflow)
                    <article class="cubicl-workflow__card" data-testid="cubicl-workflow-card" data-workflow-id="{{ $workflow->id }}">
                        <header class="cubicl-workflow__card-header">
                            <div class="cubicl-workflow__card-identity">
                                <h3>{{ $workflow->name }}</h3>
                                <span
                                    class="cubicl-workflow__state cubicl-workflow__state--{{ $workflow->is_active ? 'active' : 'inactive' }}"
                                    data-testid="cubicl-workflow-state"
                                >
                                    <x-filament::icon
                                        :icon="$workflow->is_active ? 'heroicon-o-check-circle' : 'heroicon-o-pause-circle'"
                                        aria-hidden="true"
                                    />
                                    {{ $workflow->is_active ? __('management.workflow_setup.card.active') : __('management.workflow_setup.card.inactive') }}
                                </span>
                            </div>

                            <div class="cubicl-workflow__card-meta">
                                <span>{{ __('management.workflow_setup.card.step_count', ['count' => $workflow->steps->count()]) }}</span>
                                <span>{{ __('management.workflow_setup.card.linked_services', ['count' => $workflow->program_versions_count]) }}</span>
                                <a
                                    href="{{ \App\Filament\Resources\ServiceWorkflows\ServiceWorkflowResource::getUrl('edit', ['record' => $workflow]) }}"
                                    class="cubicl-workflow__card-edit"
                                    wire:navigate
                                    data-testid="cubicl-workflow-edit"
                                >
                                    <x-filament::icon icon="heroicon-o-pencil-square" aria-hidden="true" />
                                    <span>{{ __('management.workflow_setup.card.edit') }}</span>
                                </a>
                            </div>
                        </header>

                        @if ($workflow->steps->isNotEmpty())
                            <ol
                                class="cubicl-workflow__stages"
                                aria-label="{{ __('management.workflow_setup.card.steps_label', ['name' => $workflow->name]) }}"
                            >
                                @foreach ($workflow->steps as $step)
                                    <li class="cubicl-workflow__stage" data-testid="cubicl-workflow-stage" data-step-type="{{ $step->type }}">
                                        <span class="cubicl-workflow__stage-head">
                                            <span class="cubicl-workflow__stage-type cubicl-workflow__stage-type--{{ $step->type }}">
                                                <x-filament::icon :icon="$stepTypeIcons[$step->type] ?? 'heroicon-o-play'" aria-hidden="true" />
                                                <span>{{ $stepTypeLabels[$step->type] ?? $step->type }}</span>
                                            </span>
                                            @if (filled($step->attention_note))
                                                <span class="cubicl-workflow__stage-note" title="{{ $step->attention_note }}">
                                                    <x-filament::icon icon="heroicon-o-exclamation-triangle" aria-hidden="true" />
                                                    <span class="fi-sr-only">{{ $step->attention_note }}</span>
                                                </span>
                                            @endif
                                        </span>
                                        <span class="cubicl-workflow__stage-title">{{ $step->title }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <p class="cubicl-workflow__stages-empty">{{ __('management.workflow_setup.card.no_steps') }}</p>
                        @endif

                        <a
                            href="{{ \App\Filament\Resources\ServiceWorkflows\ServiceWorkflowResource::getUrl('edit', ['record' => $workflow, 'yeniAsama' => 1]) }}"
                            class="cubicl-workflow__stage-add"
                            wire:navigate
                            data-testid="cubicl-workflow-add-stage"
                        >
                            <x-filament::icon icon="heroicon-o-plus" aria-hidden="true" />
                            <span>{{ __('management.workflow_setup.card.add_step') }}</span>
                        </a>
                    </article>
                @empty
                    <p class="cubicl-workflow__empty" data-testid="cubicl-workflow-empty">
                        @if (trim($this->workflowSearch) !== '')
                            {{ __('management.workflow_setup.search.empty') }}
                        @else
                            {{ __('management.workflow_setup.empty.description') }}
                        @endif
                    </p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
