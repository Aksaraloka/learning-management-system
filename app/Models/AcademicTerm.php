<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\AcademicTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $tenant_id
 * @property string $name
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_locked
 */
#[Fillable(['name', 'starts_at', 'ends_at', 'is_locked'])]
class AcademicTerm extends Model
{
    /** @use HasFactory<AcademicTermFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_locked' => 'boolean',
        ];
    }
}
