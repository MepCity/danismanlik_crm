<?php

declare(strict_types=1);

namespace App\Support\Workflow;

use Illuminate\Support\Carbon;

interface WorkflowSubjectGateway
{
    public function lock(int $subjectId): WorkflowSubject;

    public function updateStatus(int $subjectId, int $statusId, Carbon $changedAt): void;
}
