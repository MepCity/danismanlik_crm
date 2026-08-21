<?php

declare(strict_types=1);

namespace App\Domain\Program\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_workflow_id
 * @property string $type
 * @property string $title
 * @property string $guidance
 * @property string|null $attention_note
 * @property int $sort_order
 * @property bool $is_active
 * @property-read ServiceWorkflow $workflow
 */
#[Fillable([
    'service_workflow_id', 'type', 'title', 'guidance', 'attention_note',
    'sort_order', 'is_active',
])]
final class ServiceWorkflowStep extends Model
{
    /** @return BelongsTo<ServiceWorkflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ServiceWorkflow::class, 'service_workflow_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
