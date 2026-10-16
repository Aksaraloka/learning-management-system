<?php

namespace App\Livewire\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\User;
use App\Support\AttendanceSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Daftar sesi presensi sebuah mata kuliah. Pengelola membuat sesi dan mengisinya; mahasiswa hanya melihat
 * kehadirannya sendiri; ketua program studi hanya melihat.
 */
class Index extends Component
{
    #[Locked]
    public Course $course;

    public string $held_on = '';

    public string $section_id = '';

    public string $title = '';

    public function mount(Course $course): void
    {
        Gate::authorize('view', $course);

        $this->course = $course;
        $this->held_on = now()->timezone(config('app.timezone'))->format('Y-m-d');
    }

    public function createSession(): void
    {
        Gate::authorize('manageAttendance', $this->course);

        $this->validate([
            'held_on' => ['required', 'date_format:Y-m-d'],
            'section_id' => ['nullable', Rule::in($this->course->sections()->pluck('id')->map(fn ($id) => (string) $id)->all())],
            'title' => ['nullable', 'string', 'max:255'],
        ], [], ['held_on' => __('attendance.date'), 'section_id' => term('section'), 'title' => __('attendance.session_title')]);

        $session = AttendanceSession::create([
            'course_id' => $this->course->id,
            'section_id' => $this->section_id !== '' ? (int) $this->section_id : null,
            'held_on' => $this->held_on,
            'title' => trim($this->title) !== '' ? trim($this->title) : null,
        ]);

        $this->redirectRoute('attendance.session', ['course' => $this->course, 'session' => $session]);
    }

    public function deleteSession(int $id): void
    {
        Gate::authorize('manageAttendance', $this->course);

        abort_if((bool) $this->course->term->is_locked, 403);

        AttendanceSession::query()->where('course_id', $this->course->id)->findOrFail($id)->delete();
    }

    public function render(): View
    {
        $user = Auth::user();
        $canManage = Gate::allows('manageAttendance', $this->course);
        $canRoster = Gate::allows('viewRoster', $this->course);
        $isStudent = ! $canRoster && $user->hasAppRole(Role::Student);

        // Presensi resmi dari Academic: rekap read-only; sesi lokal tidak ditampilkan (datanya tidak dihapus).
        $official = AttendanceSummary::fetch($this->course);

        $sessions = $official['official']
            ? collect()
            : AttendanceSession::query()->where('course_id', $this->course->id)->with('section')
                ->withCount([
                    'records',
                    'records as present_count' => fn ($q) => $q->where('status', AttendanceStatus::Present->value),
                ])
                ->orderByDesc('held_on')->orderByDesc('id')->get();

        $mine = $isStudent
            ? AttendanceRecord::query()->where('user_id', $user->id)->whereIn('attendance_session_id', $sessions->pluck('id'))->get()->keyBy('attendance_session_id')
            : collect();

        return view('livewire.attendance.index', [
            'sessions' => $sessions,
            'canManage' => $canManage,
            'isStudent' => $isStudent,
            'mine' => $mine,
            'summary' => $isStudent ? ($official['rows'][$user->id] ?? null) : null,
            'official' => $official['official'],
            'available' => $official['available'],
            'recap' => $official['official'] && $canRoster
                ? $this->course->students()->reorder('users.name')->get()->map(fn (User $student) => ['student' => $student, 'row' => $official['rows'][$student->id] ?? null])
                : null,
            'studentCount' => $this->course->students()->count(),
            'sections' => $this->course->sections()->pluck('title', 'id')->all(),
            'locked' => (bool) $this->course->term->is_locked,
        ])->title(__('attendance.title').' · '.$this->course->name);
    }
}
