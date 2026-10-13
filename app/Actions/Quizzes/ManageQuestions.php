<?php

namespace App\Actions\Quizzes;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\Quiz;
use App\Support\StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

class ManageQuestions
{
    /**
     * Menyimpan soal. Isi dari form diterjemahkan ke `options` dan `answer_key` sesuai tipe soal:
     *  - multiple_choice: options [{id, text}], answer_key {correct: id}
     *  - true_false:      answer_key {correct: bool}
     *  - short_answer:    answer_key {accepted: [..], case_sensitive: bool}
     *  - essay:           answer_key {rubric: string|null} (hanya dilihat dosen)
     * Id pilihan dipertahankan per urutan saat soal diedit agar jawaban yang sudah tersimpan tetap valid.
     *
     * @param  array{type: QuestionType, body: string, points: float, options?: list<string>, correct_index?: int, true_false?: bool, accepted?: list<string>, case_sensitive?: bool, rubric?: string|null}  $data
     */
    public function save(Quiz $quiz, array $data, ?UploadedFile $image = null, bool $removeImage = false, ?Question $question = null): Question
    {
        $type = $data['type'];
        $options = null;
        $key = null;

        switch ($type) {
            case QuestionType::MultipleChoice:
                $existing = array_column($question->options ?? [], 'id');
                $options = [];

                foreach ($data['options'] ?? [] as $i => $text) {
                    $options[] = ['id' => (string) ($existing[$i] ?? Str::lower(Str::random(8))), 'text' => trim($text)];
                }

                $key = ['correct' => $options[(int) ($data['correct_index'] ?? 0)]['id'] ?? $options[0]['id']];
                break;

            case QuestionType::TrueFalse:
                $key = ['correct' => (bool) ($data['true_false'] ?? true)];
                break;

            case QuestionType::ShortAnswer:
                $key = [
                    'accepted' => array_values(array_filter(array_map('trim', $data['accepted'] ?? []), fn ($a) => $a !== '')),
                    'case_sensitive' => (bool) ($data['case_sensitive'] ?? false),
                ];
                break;

            case QuestionType::Essay:
                $rubric = trim((string) ($data['rubric'] ?? ''));
                $key = $rubric !== '' ? ['rubric' => $rubric] : null;
                break;
        }

        return DB::transaction(function () use ($quiz, $data, $type, $options, $key, $image, $removeImage, $question) {
            $attributes = [
                'type' => $type,
                'body' => Purifier::clean($data['body'], 'content'),
                'points' => round($data['points'], 2),
                'options' => $options,
                'answer_key' => $key,
            ];

            $oldImage = $question?->image_path;

            if ($image) {
                $stored = StoredFile::store($image, "courses/{$quiz->course_id}/quizzes/{$quiz->id}");
                $attributes += ['image_path' => $stored['file_path'], 'image_name' => $stored['file_name'], 'image_mime' => $stored['mime_type']];
            } elseif ($removeImage) {
                $attributes += ['image_path' => null, 'image_name' => null, 'image_mime' => null];
            }

            if ($question) {
                $question->update($attributes);
            } else {
                $question = $quiz->questions()->create($attributes + [
                    'position' => (int) $quiz->questions()->max('position') + 1,
                ]);
            }

            if ($oldImage && $oldImage !== $question->image_path) {
                StoredFile::delete($oldImage);
            }

            return $question;
        });
    }

    public function delete(Question $question): void
    {
        StoredFile::delete($question->image_path);

        $question->delete();
    }

    /**
     * Urutkan ulang. Id yang bukan milik kuis ini diabaikan.
     *
     * @param  list<int|string>  $ids
     */
    public function reorder(Quiz $quiz, array $ids): void
    {
        $valid = $quiz->questions()->pluck('id')->all();
        $ordered = array_values(array_filter(array_map('intval', $ids), fn (int $id) => in_array($id, $valid, true)));
        $rest = array_values(array_diff($valid, $ordered));

        DB::transaction(function () use ($ordered, $rest) {
            foreach (array_merge($ordered, $rest) as $i => $id) {
                Question::query()->whereKey($id)->update(['position' => $i + 1]);
            }
        });
    }
}
