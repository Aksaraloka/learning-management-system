<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quiz_id
 * @property QuestionType $type
 * @property string $body
 * @property list<array{id: string, text: string}>|null $options
 * @property array<string, mixed>|null $answer_key
 * @property float $points
 * @property int $position
 * @property string|null $image_path
 * @property string|null $image_name
 * @property string|null $image_mime
 * @property-read Quiz $quiz
 */
#[Fillable(['quiz_id', 'type', 'body', 'options', 'answer_key', 'points', 'position', 'image_path', 'image_name', 'image_mime'])]
class Question extends Model
{
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'options' => 'array',
            'answer_key' => 'array',
            'points' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class)->withTrashed();
    }

    /**
     * Pilihan tanpa informasi kunci, aman dikirim ke peserta. Urutan mengikuti $order (acak per percobaan) bila ada.
     *
     * @param  list<string>|null  $order
     * @return list<array{id: string, text: string}>
     */
    public function optionsFor(?array $order = null): array
    {
        $options = collect($this->options ?? [])->map(fn (array $o) => ['id' => (string) $o['id'], 'text' => (string) $o['text']]);

        if ($order) {
            $byId = $options->keyBy('id');
            $sorted = collect($order)->map(fn (string $id) => $byId->get($id))->filter();
            $options = $sorted->concat($options->reject(fn ($o) => in_array($o['id'], $order, true)));
        }

        return $options->values()->all();
    }

    public function hasImage(): bool
    {
        return $this->image_path !== null;
    }
}
