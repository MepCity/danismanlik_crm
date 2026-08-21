<x-filament-panels::page>
    @php($program = $this->record->load(['latestVersion.serviceWorkflow.steps', 'latestVersion.docTemplates']))
    @php($version = $program->latestVersion)
    <div class="company-detail">
        <section class="operations-panel operations-facts">
            <div class="operations-fact"><dt>{{ __('management.fields.institution') }}</dt><dd>{{ __('management.institutions.'.$program->institution) }}</dd></div>
            <div class="operations-fact"><dt>{{ __('management.fields.status') }}</dt><dd>{{ $program->is_active ? __('management.messages.active') : __('management.messages.inactive') }}</dd></div>
            <div class="operations-fact"><dt>{{ __('management.fields.call_period') }}</dt><dd>{{ $version?->call_period ?? __('management.messages.not_set') }}</dd></div>
            <div class="operations-fact"><dt>{{ __('management.fields.service_workflow') }}</dt><dd>{{ $version?->serviceWorkflow?->name ?? __('management.messages.not_set') }}</dd></div>
            <div class="operations-fact"><dt>{{ __('management.fields.template_count') }}</dt><dd class="numeric-data">{{ $version?->docTemplates->where('is_active', true)->count() ?? 0 }}</dd></div>
        </section>

        <nav class="deal-tabs" aria-label="{{ $program->name }}">
            @foreach (['workflow', 'comments', 'history'] as $tab)
                <button type="button" wire:click="$set('activeTab', '{{ $tab }}')" class="deal-tab {{ $activeTab === $tab ? 'deal-tab--active' : '' }}">{{ __('management.program_start.tabs.'.$tab) }}</button>
            @endforeach
        </nav>

        @if ($activeTab === 'workflow')
            <section class="checklist-table-wrap">
                <table class="checklist-table">
                    <thead><tr><th>{{ __('management.fields.sort_order') }}</th><th>{{ __('management.fields.step_title') }}</th><th>{{ __('management.fields.step_type') }}</th><th>{{ __('management.fields.step_guidance') }}</th><th>{{ __('management.fields.attention_note') }}</th></tr></thead>
                    <tbody>
                    @forelse($version?->serviceWorkflow?->steps?->where('is_active', true) ?? [] as $step)
                        <tr><td class="numeric-data">{{ $loop->iteration }}</td><td>{{ $step->title }}</td><td>{{ __('management.workflow_step_types.'.$step->type) }}</td><td>{{ $step->guidance }}</td><td>{{ $step->attention_note ?? __('management.messages.not_set') }}</td></tr>
                    @empty
                        <tr><td colspan="5">{{ __('management.program_start.no_workflow') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>
        @elseif ($activeTab === 'comments')
            <livewire:collaboration-comments subject-type="program" :subject-id="$program->id" :key="'program-comments-'.$program->id" />
        @elseif ($activeTab === 'history')
            <livewire:collaboration-timeline subject-type="program" :subject-id="$program->id" :key="'program-timeline-'.$program->id" />
        @endif
    </div>
</x-filament-panels::page>
