<?php

declare(strict_types=1);

namespace App\Support\Collaboration;

use App\Domain\Collaboration\DTOs\SubjectReference;
use App\Domain\Collaboration\Enums\CollaborationSubjectType;
use App\Domain\Crm\Models\Company;
use App\Domain\Crm\Models\Lead;
use App\Domain\Deal\Models\Deal;
use App\Domain\Document\Models\DealDocument;
use Illuminate\Database\Eloquent\Model;

final class SubjectModelResolver
{
    public function resolve(SubjectReference $subject): Model
    {
        return match ($subject->type) {
            CollaborationSubjectType::Company => Company::query()->findOrFail($subject->id),
            CollaborationSubjectType::Lead => Lead::query()->findOrFail($subject->id),
            CollaborationSubjectType::Deal => Deal::query()->findOrFail($subject->id),
            CollaborationSubjectType::DealDocument => DealDocument::query()->findOrFail($subject->id),
        };
    }
}
