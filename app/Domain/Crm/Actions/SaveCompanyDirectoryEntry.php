<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Collaboration\Services\ActivityRecorder;
use App\Domain\Collaboration\Services\CollaborationTransaction;
use App\Domain\Crm\Models\Company;
use App\Models\User;
use App\Support\Audit\ActorSource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final readonly class SaveCompanyDirectoryEntry
{
    public function __construct(
        private ActivityRecorder $activities,
        private CollaborationTransaction $transactions,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(?Company $company, array $data, User $actor): Company
    {
        Gate::forUser($actor)->authorize($company === null ? 'create' : 'update', $company ?? Company::class);

        $validated = Validator::make($data, [
            'legal_name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', Rule::in(config('operations.company_industries'))],
            'city' => ['required', 'string', Rule::in(config('turkey.provinces'))],
            'district' => ['nullable', 'string', 'max:255'],
            'tax_number' => [
                'nullable',
                'regex:/^[0-9]{10}([0-9])?$/',
                Rule::unique('companies', 'tax_number')->ignore($company?->id),
            ],
            'tax_office' => ['nullable', 'string', 'max:255'],
            'nace_code' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', Rule::in(['micro', 'small', 'medium', 'large'])],
            'employee_count' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ])->validate();

        return $this->transactions->run(ActorSource::User, $actor->id, function () use ($company, $validated, $actor): Company {
            $isNew = $company === null;
            $company ??= new Company;

            if ($isNew) {
                $company->owner_user_id = $actor->id;
            }

            $company->fill($validated);
            $company->save();

            $this->activities->record(
                action: $isNew ? 'company.created' : 'company.updated',
                payload: [
                    'company' => ['id' => $company->id, 'name' => $company->legal_name],
                    'industry' => $company->industry,
                ],
                actorId: $actor->id,
                defaultSource: 'user',
                companyId: $company->id,
            );

            return $company->refresh();
        });
    }
}
