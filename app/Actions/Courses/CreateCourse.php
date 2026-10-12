<?php

namespace App\Actions\Courses;

use App\Enums\EnrollmentRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCourse
{
    /**
     * Membuat kelas beserta kode gabung dan mendaftarkan pengajarnya.
     *
     * @param  array{academic_term_id: int, name: string, credits?: int|null, description?: string|null}  $data
     */
    public function handle(array $data, User $teacher): Course
    {
        return DB::transaction(function () use ($data, $teacher) {
            $course = Course::create($data + ['code' => Course::generateCode()]);

            Enrollment::create([
                'course_id' => $course->id,
                'user_id' => $teacher->id,
                'role' => EnrollmentRole::Teacher,
            ]);

            return $course;
        });
    }
}
