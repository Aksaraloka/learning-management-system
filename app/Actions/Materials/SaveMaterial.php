<?php

namespace App\Actions\Materials;

use App\Enums\MaterialType;
use App\Models\Material;
use App\Models\Section;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mews\Purifier\Facades\Purifier;

class SaveMaterial
{
    /**
     * Membuat atau memperbarui materi. Isi teks disanitasi (Purifier), berkas disimpan di disk kelas
     * dengan nama acak (nama asli disimpan di database), dan berkas lama dihapus saat diganti.
     *
     * @param  array{type: MaterialType, title: string, body?: string|null, url?: string|null}  $data
     */
    public function handle(Section $section, array $data, ?UploadedFile $file = null, ?Material $material = null): Material
    {
        $type = $data['type'];

        $attributes = [
            'type' => $type,
            'title' => trim($data['title']),
            'body' => $type === MaterialType::Text ? Purifier::clean((string) ($data['body'] ?? ''), 'content') : null,
            'url' => in_array($type, [MaterialType::Link, MaterialType::Video], true) ? trim((string) ($data['url'] ?? '')) : null,
        ];

        return DB::transaction(function () use ($section, $attributes, $type, $file, $material) {
            $disk = Storage::disk(config('courses.disk'));
            $oldPath = $material?->file_path;

            if ($type !== MaterialType::File) {
                $attributes += ['file_path' => null, 'file_name' => null, 'file_size' => null, 'mime_type' => null];
            } elseif ($file) {
                $section->loadMissing('course');

                $attributes += [
                    'file_path' => $file->store("courses/{$section->course_id}", ['disk' => config('courses.disk')]),
                    'file_name' => mb_substr(basename((string) $file->getClientOriginalName()), 0, 150),
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ];
            }

            if ($material) {
                $material->update($attributes);
            } else {
                $material = $section->materials()->create($attributes + [
                    'position' => ($section->materials()->max('position') ?? 0) + 1,
                ]);
            }

            if ($oldPath && $oldPath !== $material->file_path) {
                $disk->delete($oldPath);
            }

            return $material;
        });
    }

    /**
     * Urutkan ulang materi dalam satu topik. Id yang bukan milik topik ini diabaikan.
     *
     * @param  list<int|string>  $ids
     */
    public function reorder(Section $section, array $ids): void
    {
        $valid = $section->materials()->pluck('id')->all();
        $ordered = array_values(array_filter(array_map('intval', $ids), fn (int $id) => in_array($id, $valid, true)));
        $rest = array_values(array_diff($valid, $ordered));

        DB::transaction(function () use ($ordered, $rest) {
            foreach (array_merge($ordered, $rest) as $i => $id) {
                Material::query()->whereKey($id)->update(['position' => $i + 1]);
            }
        });
    }
}
