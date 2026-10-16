<?php

namespace App\Livewire\Attendance;

use App\Actions\Attendance\SaveAttendance;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceSession;
use App\Models\Course;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Mengisi presensi satu sesi. Pengelola mengubah; ketua program studi hanya melihat (tombol tidak aktif).
 */
class Session extends Component
{
    #[Locked]
    public Course $course;

    #[Locked]
    public AttendanceSession $session;

    /** @var array<int|string, string> status per id mahasiswa */
    public array $statuses = [];

    /** @var array<int|string, string> catatan per id mahasiswa */
    public array $notes = [];

    public function mount(Course $course, AttendanceSession $session): void
    {
        abort_unless($session->course_id === $course->id, 404);

        Gate::authorize('viewRoster', $course);

        $this->course = $course;
        $this->session = $session;

        foreach ($session->records as $record) {
            $this->statuses[$record->user_id] = $record->status->value;
            $this->notes[$record->user_id] = (string) $record->note;
        }
    }

    public function setStatus(int $userId, string $status): void
    {
        Gate::authorize('manageAttendance', $this->course);

        if (AttendanceStatus::tryFrom($status)) {
            $this->statuses[$userId] = $status;
        }
    }

    public function markAll(string $status): void
    {
        Gate::authorize('manageAttendance', $this->course);

        if (! AttendanceStatus::tryFrom($status)) {
            return;
        }

        foreach ($this->course->students()->pluck('users.id') as $id) {
            $this->statuses[$id] = $status;
        }
    }

    public function save(SaveAttendance $save): void
    {
        Gate::authorize('manageAttendance', $this->course);

        $entries = [];

        foreach ($this->statuses as $userId => $status) {
            $entries[$userId] = ['status' => (string) $status, 'note' => $this->notes[$userId] ?? null];
        }

        $count = $save->handle($this->session, $entries);

        $this->dispatch('toast', message: __('attendance.saved', ['n' => $count]), type: 'success');
    }

    public function render(): View
    {
        $students = $this->course->students()->reorder('users.name')->get();
        $counts = array_count_values(array_map('strval', $this->statuses));

        return view('livewire.attendance.session', [
            'students' => $students,
            'canManage' => Gate::allows('manageAttendance', $this->course),
            'locked' => (bool) $this->course->term()->first()?->is_locked,
            'counts' => $counts,
            'statusCases' => AttendanceStatus::cases(),
        ])->title(__('attendance.title').' · '.$this->course->name);
    }
}
