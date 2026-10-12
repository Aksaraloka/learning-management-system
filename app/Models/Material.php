<?php

namespace App\Models;

use App\Enums\MaterialType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $section_id
 * @property MaterialType $type
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $file_size
 * @property string|null $mime_type
 * @property int $position
 * @property-read Section $section
 */
#[Fillable(['section_id', 'type', 'title', 'body', 'url', 'file_path', 'file_name', 'file_size', 'mime_type', 'position'])]
class Material extends Model
{
    protected function casts(): array
    {
        return ['type' => MaterialType::class];
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
