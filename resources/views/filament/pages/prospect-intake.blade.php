<x-filament-panels::page>
    <form wire:submit="save" class="intake-layout" data-testid="prospect-intake">
        <div class="intake-main">
            <section class="operations-panel intake-section">
                <header class="intake-section__header"><span class="intake-step">1</span><div><h2>{{ __('marketing.intake.company.title') }}</h2><p>{{ __('marketing.intake.company.description') }}</p></div></header>
                <div class="intake-choice">
                    <label class="intake-choice__item {{ $companyMode === 'new' ? 'intake-choice__item--active' : '' }}"><input type="radio" wire:model.live="companyMode" value="new">{{ __('marketing.intake.company.new') }}</label>
                    <label class="intake-choice__item {{ $companyMode === 'existing' ? 'intake-choice__item--active' : '' }}"><input type="radio" wire:model.live="companyMode" value="existing">{{ __('marketing.intake.company.existing') }}</label>
                </div>
                <div class="intake-fields intake-fields--three">
                    @if ($companyMode === 'existing')
                        <label class="intake-field intake-field--wide"><span>{{ __('marketing.intake.company.choose') }}</span><select wire:model.live="companyId" required><option value="">{{ __('marketing.intake.choose') }}</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->legal_name }} · {{ $company->city }}</option>@endforeach</select></label>
                    @else
                        <label class="intake-field intake-field--wide"><span>{{ __('panel.fields.legal_name') }}</span><input wire:model.live.debounce.350ms="companyName" required></label>
                        <label class="intake-field"><span>{{ __('marketing.intake.company.tax_number') }}</span><input class="numeric-data" wire:model.live.debounce.350ms="taxNumber" inputmode="numeric"></label>
                        <label class="intake-field"><span>{{ __('panel.fields.city') }}</span><select wire:model="city" required><option value="">{{ __('marketing.intake.choose') }}</option>@foreach($provinces as $province)<option value="{{ $province }}">{{ $province }}</option>@endforeach</select></label>
                    @endif
                </div>
                @if ($companyMode === 'new' && $duplicateCompanies->isNotEmpty())
                    <div class="intake-duplicate" role="status"><strong>{{ __('marketing.intake.company.possible_duplicate') }}</strong><p>{{ __('marketing.intake.company.possible_duplicate_help') }}</p><div>@foreach($duplicateCompanies as $duplicate)<button type="button" class="operations-link" wire:click="$set('companyMode', 'existing'); $set('companyId', {{ $duplicate->id }})">{{ $duplicate->legal_name }} · {{ $duplicate->city }}</button>@endforeach</div></div>
                @endif
            </section>

            <section class="operations-panel intake-section">
                <header class="intake-section__header"><span class="intake-step">2</span><div><h2>{{ __('marketing.intake.contact.title') }}</h2><p>{{ __('marketing.intake.contact.description') }}</p></div></header>
                @if ($companyMode === 'existing' && $companyId && $contacts->isNotEmpty())
                    <div class="intake-choice">
                        <label class="intake-choice__item {{ $contactMode === 'new' ? 'intake-choice__item--active' : '' }}"><input type="radio" wire:model.live="contactMode" value="new">{{ __('marketing.intake.contact.new') }}</label>
                        <label class="intake-choice__item {{ $contactMode === 'existing' ? 'intake-choice__item--active' : '' }}"><input type="radio" wire:model.live="contactMode" value="existing">{{ __('marketing.intake.contact.existing') }}</label>
                    </div>
                @endif
                @if ($companyMode === 'existing' && $contactMode === 'existing' && $contacts->isNotEmpty())
                    <label class="intake-field"><span>{{ __('marketing.intake.contact.choose') }}</span><select wire:model="contactId" required><option value="">{{ __('marketing.intake.choose') }}</option>@foreach($contacts as $contact)<option value="{{ $contact->id }}">{{ $contact->full_name }} · {{ $contact->title }}</option>@endforeach</select></label>
                @else
                    <div class="intake-fields intake-fields--two">
                        <label class="intake-field"><span>{{ __('marketing.contacts.full_name') }}</span><input wire:model="contactName" required></label>
                        <label class="intake-field"><span>{{ __('marketing.contacts.title') }}</span><input wire:model="contactTitle" required></label>
                        <label class="intake-field"><span>{{ __('marketing.contacts.phone') }}</span><input class="numeric-data" wire:model="phone" required></label>
                        <label class="intake-field"><span>{{ __('marketing.contacts.email') }}</span><input type="email" wire:model="email" required></label>
                        <label class="intake-field"><span>{{ __('marketing.contacts.call_consent') }}</span><select wire:model="callConsent">@foreach(__('marketing.contacts.consent_options') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                        <label class="intake-field"><span>{{ __('marketing.contacts.disclosure_date') }}</span><input type="date" wire:model="disclosureDate"></label>
                    </div>
                @endif
            </section>

            <section class="operations-panel intake-section">
                <header class="intake-section__header"><span class="intake-step">3</span><div><h2>{{ __('marketing.intake.opportunity.title') }}</h2><p>{{ __('marketing.intake.opportunity.description') }}</p></div></header>
                <div class="intake-fields intake-fields--two">
                    <label class="intake-field"><span>{{ __('marketing.detail.program') }}</span><select wire:model="programVersionId" required><option value="">{{ __('marketing.transition.choose_program') }}</option>@foreach($programVersions as $version)<option value="{{ $version->id }}">{{ $version->program->name }} · {{ $version->call_period }}</option>@endforeach</select></label>
                    <label class="intake-field"><span>{{ __('marketing.detail.status') }}</span><select wire:model.live="targetStatusId" required>@foreach($statusOptions as $status)<option value="{{ $status->id }}">{{ $status->label }}</option>@endforeach</select></label>
                    @if ($selectedStatus && in_array('next_call_at', $selectedStatus->required_fields, true))<label class="intake-field"><span>{{ __('marketing.transition.next_call_at') }}</span><input type="datetime-local" wire:model="nextCallAt" required></label>@endif
                </div>
            </section>

            <section class="operations-panel intake-section">
                <header class="intake-section__header"><span class="intake-step">4</span><div><h2>{{ __('marketing.intake.call.title') }}</h2><p>{{ __('marketing.intake.call.description') }}</p></div></header>
                <div class="intake-fields intake-fields--three">
                    <label class="intake-field"><span>{{ __('marketing.interactions.date') }}</span><input type="datetime-local" wire:model="calledAt" required></label>
                    <label class="intake-field"><span>{{ __('marketing.intake.call.direction') }}</span><select wire:model="callDirection"><option value="outbound">{{ __('marketing.intake.call.outbound') }}</option><option value="inbound">{{ __('marketing.intake.call.inbound') }}</option></select></label>
                    <label class="intake-field"><span>{{ __('marketing.interactions.outcome') }}</span><select wire:model="outcome">@foreach(__('marketing.interactions.outcomes') as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    <label class="intake-field intake-field--wide"><span>{{ __('marketing.intake.call.note') }}</span><textarea rows="5" wire:model="callNote" required placeholder="{{ __('marketing.intake.call.note_placeholder') }}"></textarea></label>
                    <label class="intake-field intake-field--wide"><span>{{ __('marketing.intake.company_comment') }}</span><textarea rows="3" wire:model="companyComment" placeholder="{{ __('marketing.intake.company_comment_placeholder') }}"></textarea></label>
                </div>
            </section>

            <section class="operations-panel intake-section">
                <header class="intake-section__header"><span class="intake-step">5</span><div><h2>{{ __('marketing.intake.reminder.title') }}</h2><p>{{ __('marketing.intake.reminder.description') }}</p></div></header>
                <div class="intake-fields intake-fields--three">
                    <label id="field-task-title" class="intake-field"><span>{{ __('collaboration.tasks.title') }}</span><input wire:model.live.debounce.300ms="taskTitle">@error('taskTitle')<small class="operations-field-error">{{ $message }}</small>@enderror</label>
                    <label id="field-task-due-at" class="intake-field {{ $errors->has('taskDueAt') ? 'intake-field--invalid' : '' }}"><span>{{ __('marketing.intake.reminder.due_at') }}</span><input type="datetime-local" wire:model="taskDueAt" aria-invalid="{{ $errors->has('taskDueAt') ? 'true' : 'false' }}">@error('taskDueAt')<small class="operations-field-error" role="alert">{{ $message }}</small>@enderror</label>
                    <label id="field-task-remind-at" class="intake-field {{ $errors->has('taskRemindAt') ? 'intake-field--invalid' : '' }}"><span>{{ __('marketing.intake.reminder.at') }}</span><input type="datetime-local" wire:model="taskRemindAt">@error('taskRemindAt')<small class="operations-field-error" role="alert">{{ $message }}</small>@enderror</label>
                </div>
            </section>
        </div>

        <aside class="operations-panel intake-summary">
            <span class="operations-label">{{ __('marketing.intake.summary.eyebrow') }}</span>
            <h2>{{ __('marketing.intake.summary.title') }}</h2>
            <ol><li>{{ __('marketing.intake.summary.company') }}</li><li>{{ __('marketing.intake.summary.contact') }}</li><li>{{ __('marketing.intake.summary.lead') }}</li><li>{{ __('marketing.intake.summary.history') }}</li></ol>
            @if ($errors->any())<div class="operations-error" role="alert"><strong>{{ __('marketing.intake.errors') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <button class="operations-button operations-button--primary" type="submit" wire:loading.attr="disabled">{{ __('marketing.intake.save') }}</button>
            <p class="operations-muted">{{ __('marketing.intake.summary.audit') }}</p>
        </aside>
    </form>
</x-filament-panels::page>
