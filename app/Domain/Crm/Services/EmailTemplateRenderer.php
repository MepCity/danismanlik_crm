<?php

declare(strict_types=1);

namespace App\Domain\Crm\Services;

use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\EmailTemplate;
use Illuminate\Validation\ValidationException;

final class EmailTemplateRenderer
{
    /** @return array<string, string> */
    public function supportedPlaceholders(): array
    {
        return [
            '{{firma_adi}}' => trans('marketing.email_templates.placeholders.company'),
            '{{firma_unvani}}' => trans('marketing.email_templates.placeholders.company'),
            '{{yetkili_adi}}' => trans('marketing.email_templates.placeholders.contact'),
            '{{sektor}}' => trans('marketing.email_templates.placeholders.industry'),
            '{{il}}' => trans('marketing.email_templates.placeholders.city'),
        ];
    }

    /** @return array{subject: string, body: string} */
    public function render(EmailTemplate $template, Contact $contact): array
    {
        $this->validate($template->subject, $template->body);
        $company = $contact->company;
        $replacements = [
            '{{firma_adi}}' => $company->legal_name,
            '{{firma_unvani}}' => $company->legal_name,
            '{{yetkili_adi}}' => $contact->full_name,
            '{{sektor}}' => trans('panel.industries.'.$company->industry),
            '{{il}}' => $company->city ?? trans('management.messages.not_set'),
        ];

        return [
            'subject' => strtr($template->subject, $replacements),
            'body' => strtr($template->body, $replacements),
        ];
    }

    public function validate(string $subject, string $body): void
    {
        preg_match_all('/\{\{[a-z_]+\}\}/u', $subject."\n".$body, $matches);
        $unknown = array_values(array_diff(array_unique($matches[0]), array_keys($this->supportedPlaceholders())));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'body' => trans('marketing.email_templates.validation.unknown', ['placeholders' => implode(', ', $unknown)]),
            ]);
        }
    }
}
