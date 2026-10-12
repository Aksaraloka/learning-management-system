<?php

namespace App\Actions\Courses;

use App\Enums\EnrollmentRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

class EnrollMembers
{
    /**
     * Mendaftarkan banyak akun sekaligus berdasarkan NIM/NIDN atau email.
     * Akun harus punya peran aplikasi yang sesuai (dosen untuk dosen, mahasiswa untuk peserta).
     *
     * @param  list<string>  $identifiers
     * @return array{added: int, missing: list<string>, ineligible: list<string>, skipped: list<string>}
     */
    public function handle(Course $course, EnrollmentRole $role, array $identifiers): array
    {
        $identifiers = array_values(array_unique(array_filter(array_map('trim', $identifiers), fn ($v) => $v !== '')));

        $users = User::query()->with('roles')
            ->where(fn ($q) => $q->whereIn('identity_number', $identifiers)->orWhereIn('email', $identifiers))
            ->get();

        $byKey = [];

        foreach ($users as $user) {
            foreach ([$user->identity_number, $user->email] as $key) {
                if ($key !== null) {
                    $byKey[mb_strtolower((string) $key)] = $user;
                }
            }
        }

        $enrolled = array_fill_keys($course->enrollments()->pluck('user_id')->all(), true);
        $result = ['added' => 0, 'missing' => [], 'ineligible' => [], 'skipped' => []];

        foreach ($identifiers as $identifier) {
            $user = $byKey[mb_strtolower($identifier)] ?? null;

            if (! $user) {
                $result['missing'][] = $identifier;
            } elseif (! $user->hasAppRole($role->requiredRole())) {
                $result['ineligible'][] = $identifier;
            } elseif (isset($enrolled[$user->id])) {
                $result['skipped'][] = $identifier;
            } else {
                Enrollment::create(['course_id' => $course->id, 'user_id' => $user->id, 'role' => $role]);
                $enrolled[$user->id] = true;
                $result['added']++;
            }
        }

        return $result;
    }
}
