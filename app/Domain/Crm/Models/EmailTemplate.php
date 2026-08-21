<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Support\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

/**
 * @property int $id
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property bool $is_active
 */
#[Fillable(['name', 'subject', 'body', 'is_active'])]
final class EmailTemplate extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
