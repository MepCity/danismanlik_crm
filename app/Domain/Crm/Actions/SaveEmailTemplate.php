<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Collaboration\Services\CollaborationTransaction;
use App\Domain\Crm\Models\EmailTemplate;
use App\Domain\Crm\Services\EmailTemplateRenderer;
use App\Models\User;
use App\Support\Audit\ActorSource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SaveEmailTemplate
{
    public function __construct(
        private EmailTemplateRenderer $renderer,
        private CollaborationTransaction $transactions,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(?EmailTemplate $template, array $data, User $actor): EmailTemplate
    {
        $template === null
            ? Gate::forUser($actor)->authorize('create', EmailTemplate::class)
            : Gate::forUser($actor)->authorize('update', $template);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255', Rule::unique('email_templates', 'name')->ignore($template?->id)],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ])->validate();
        $this->renderer->validate($validated['subject'], $validated['body']);

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($template, $validated): EmailTemplate {
            $template ??= new EmailTemplate;
            $template->fill($validated)->save();

            return $template->refresh();
        });
    }
}
