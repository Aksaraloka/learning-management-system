<?php

namespace App\Actions\Courses;

use App\Enums\EnrollmentRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class JoinCourse
{
    private const MAX_ATTEMPTS = 10;

    /**
     * Siswa bergabung ke kelas dengan kode gabung. Percobaan kode salah dibatasi per akun.
     *
     * @throws ValidationException
     */
    public function handle(User $user, string $code): Course
    {
        $key = 'join-course:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => __('courses.join.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $normalized = Course::normalizeCode($code);
        $course = $normalized === '' ? null : Course::query()->where('code', $normalized)->first();

        if (! $course) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages(['code' => __('courses.join.invalid')]);
        }

        if ($course->hasMember($user)) {
            throw ValidationException::withMessages(['code' => __('courses.join.already')]);
        }

        Enrollment::create(['course_id' => $course->id, 'user_id' => $user->id, 'role' => EnrollmentRole::Student]);

        return $course;
    }
}
