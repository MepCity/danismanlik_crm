<section data-testid="document-checklist">
    <div class="checklist-toolbar">
        <h2>{{ __('operations.documents.title') }}</h2>
        @if (auth()->user()->can('document.upload'))
            <button type="button" wire:click="sendMissingDocuments" class="operations-button operations-button--primary">{{ __('operations.documents.send_missing') }}</button>
        @endif
    </div>

    @if ($uploadDocumentId)
        <form wire:submit="uploadDocument" class="operations-inline-form">
            <input type="file" wire:model="upload" required>
            <button class="operations-button operations-button--primary">{{ __('operations.documents.upload') }}</button>
            <button type="button" wire:click="$set('uploadDocumentId', null)" class="operations-button">{{ __('operations.documents.cancel') }}</button>
            @error('upload') <span class="operations-error">{{ $message }}</span> @enderror
        </form>
    @endif

    @if ($decisionDocumentId)
        <form wire:submit="decide" class="operations-inline-form">
            <label>{{ __('operations.documents.reason') }}
                <textarea wire:model="decisionReason" rows="2"></textarea>
            </label>
            <span class="operations-muted">{{ __('operations.documents.reason_help') }}</span>
            @error('decisionReason') <span class="operations-error">{{ $message }}</span> @enderror
            <button class="operations-button operations-button--primary">{{ __('operations.documents.save_decision') }}</button>
            <button type="button" wire:click="$set('decisionDocumentId', null)" class="operations-button">{{ __('operations.documents.cancel') }}</button>
        </form>
    @endif

    <div class="checklist-table-wrap">
        <table class="checklist-table">
            <thead><tr>
                <th>{{ __('operations.documents.name') }}</th><th>{{ __('operations.documents.required') }}</th>
                <th>{{ __('operations.documents.status') }}</th><th>{{ __('operations.documents.due') }}</th>
                <th>{{ __('operations.documents.validity') }}</th><th class="numeric-data">{{ __('operations.documents.versions') }}</th>
                <th>{{ __('operations.documents.actions') }}</th>
            </tr></thead>
            <tbody>
            @forelse ($deal->documents as $document)
                @php($status = \App\Filament\Support\DocumentStatus::get($document->status))
                <tr class="{{ $document->status === 'expired' ? 'checklist-row--expired' : '' }}">
                    <td><strong>{{ $document->name_snapshot }}</strong>
                        @if ($document->requirementSuggestions->isNotEmpty())
                            <div class="deal-warning" data-testid="document-pending-suggestion">
                                <span aria-hidden="true">!</span>{{ __('operations.documents.suggestion') }}
                                @if (auth()->user()->can('document.approve'))
                                    <button wire:click="decideSuggestion({{ $document->requirementSuggestions->first()->id }}, true)" class="operations-link">{{ __('operations.documents.approve_suggestion') }}</button>
                                    <button wire:click="decideSuggestion({{ $document->requirementSuggestions->first()->id }}, false)" class="operations-link">{{ __('operations.documents.reject_suggestion') }}</button>
                                @endif
                            </div>
                        @endif
                    </td>
                    <td>{{ $document->required_snapshot ? __('operations.documents.required_yes') : __('operations.documents.required_no') }}</td>
                    <td>{!! \App\Filament\Support\StatusBadge::make($status['token'], $status['label']) !!}</td>
                    <td class="numeric-data">{{ $document->due_at?->format('d.m.Y') ?? __('operations.documents.no_date') }}</td>
                    <td class="numeric-data">{{ $document->validity_expires_at?->format('d.m.Y') ?? __('operations.documents.no_date') }}</td>
                    <td class="numeric-data">{{ $document->files->count() }}</td>
                    <td><div class="operations-actions">
                        @if (auth()->user()->can('document.upload') && $document->status !== 'not_required')
                            <button wire:click="$set('uploadDocumentId', {{ $document->id }})" class="operations-link">{{ __('operations.documents.upload') }}</button>
                        @endif
                        @if (auth()->user()->can('document.approve') && $document->status === 'uploaded')
                            <button wire:click="startReview({{ $document->id }})" class="operations-link">{{ __('operations.documents.start_review') }}</button>
                        @endif
                        @if (auth()->user()->can('document.approve') && $document->status === 'under_review')
                            <button wire:click="$set('decisionDocumentId', {{ $document->id }}); $set('decisionTarget', 'accepted')" class="operations-link">{{ __('operations.documents.accept') }}</button>
                            <button wire:click="$set('decisionDocumentId', {{ $document->id }}); $set('decisionTarget', 'rejected')" class="operations-link">{{ __('operations.documents.reject') }}</button>
                            <button wire:click="$set('decisionDocumentId', {{ $document->id }}); $set('decisionTarget', 'new_version_expected')" class="operations-link">{{ __('operations.documents.new_version') }}</button>
                        @endif
                        <button wire:click="selectDocumentCollaboration({{ $document->id }})" class="operations-link">{{ __('operations.documents.collaboration') }}</button>
                    </div></td>
                </tr>
                @if ($document->files->isNotEmpty())
                    <tr class="version-row"><td colspan="7">
                        <details><summary>{{ __('operations.documents.history') }}</summary>
                            @foreach ($document->files as $file)
                                <div class="version-line">
                                    <span>{{ __('operations.documents.version_line', ['document' => $document->name_snapshot, 'version' => $file->version_no, 'status' => $status['label']]) }}</span>
                                    @if (auth()->user()->can('document.download') && $file->scan_result === 'clean')
                                        <button wire:click="download({{ $file->id }})" class="operations-link">{{ __('operations.documents.download') }}</button>
                                    @endif
                                </div>
                            @endforeach
                        </details>
                    </td></tr>
                @endif
            @empty
                <tr><td colspan="7" class="checklist-empty">{{ __('operations.documents.empty') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if ($collaborationDocumentId)
        @php($collaborationDocument = $deal->documents->firstWhere('id', $collaborationDocumentId))
        <section class="document-collaboration" data-testid="document-collaboration">
            <header class="collaboration-header"><div><h2>{{ $collaborationDocument?->name_snapshot }}</h2><p>{{ __('operations.documents.collaboration_description') }}</p></div><button type="button" wire:click="$set('collaborationDocumentId', null)" class="operations-link">{{ __('operations.documents.close') }}</button></header>
            <livewire:collaboration-comments subject-type="deal_document" :subject-id="$collaborationDocumentId" :key="'document-comments-'.$collaborationDocumentId" />
            <livewire:collaboration-timeline subject-type="deal_document" :subject-id="$collaborationDocumentId" :key="'document-timeline-'.$collaborationDocumentId" />
        </section>
    @endif

    @if (auth()->user()->can('document.upload'))
        <details class="operations-panel ad-hoc-form"><summary>{{ __('operations.documents.add_ad_hoc') }}</summary>
            <form wire:submit="addAdHoc">
                <label>{{ __('operations.documents.ad_hoc_name') }}<input wire:model="adHocName" required></label>
                <label>{{ __('operations.documents.ad_hoc_description') }}<textarea wire:model="adHocDescription"></textarea></label>
                <label><input type="checkbox" wire:model="adHocRequired"> {{ __('operations.documents.ad_hoc_required') }}</label>
                <button class="operations-button operations-button--primary">{{ __('operations.documents.add') }}</button>
            </form>
        </details>
    @endif
</section>
