<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\DTOs;

use App\Domain\Collaboration\Enums\CollaborationSubjectType;

final readonly class SubjectReference
{
    public function __construct(public CollaborationSubjectType $type, public int $id) {}

    /** @return array{lead_id: int|null, deal_id: int|null, deal_document_id: int|null} */
    public function columns(): array
    {
        return [
            'lead_id' => $this->type === CollaborationSubjectType::Lead ? $this->id : null,
            'deal_id' => $this->type === CollaborationSubjectType::Deal ? $this->id : null,
            'deal_document_id' => $this->type === CollaborationSubjectType::DealDocument ? $this->id : null,
        ];
    }
}
