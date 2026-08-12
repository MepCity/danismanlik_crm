<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Models;

use App\Models\User;
use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $actor_id
 * @property string $report_code
 * @property int $row_count
 * @property Carbon $created_at
 */
#[Fillable(['actor_id', 'report_code', 'row_count'])]
final class ReportExport extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
