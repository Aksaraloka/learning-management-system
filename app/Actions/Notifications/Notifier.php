<?php

namespace App\Actions\Notifications;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Notifications\Notice;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Titik tunggal pengiriman notifikasi. Penerima selalu berasal dari keanggotaan kelas; pengirim tidak
 * pernah menerima notifikasinya sendiri.
 */
class Notifier
{
    public function assignmentPosted(Assignment $assignment): void
    {
        $assignment->loadMissing('course');

        $this->send($assignment->course->students()->get(), new Notice(
            'assignment',
            __('notifications.assignment.title', ['title' => $assignment->title]),
            $this->dueText($assignment->course->name, $assignment->due_at, 'notifications.due'),
            route('assignments.show', ['course' => $assignment->course_id, 'assignment' => $assignment->id]),
        ));
    }

    /**
     * Sekali saja, saat kuis pertama kali terbit (notified_at mencegah pengiriman ulang bila diterbitkan ulang).
     */
    public function quizPublished(Quiz $quiz): void
    {
        if (! $quiz->is_published || $quiz->notified_at !== null) {
            return;
        }

        $quiz->loadMissing('course');
        $quiz->forceFill(['notified_at' => now()])->save();

        $this->send($quiz->course->students()->get(), new Notice(
            'quiz',
            __('notifications.quiz.title', ['title' => $quiz->title]),
            $this->dueText($quiz->course->name, $quiz->closes_at, 'notifications.closes'),
            route('quizzes.show', ['course' => $quiz->course_id, 'quiz' => $quiz->id]),
        ));
    }

    public function announcementPosted(Announcement $announcement): void
    {
        $announcement->loadMissing('course');

        $recipients = $announcement->course
            ? User::query()->whereIn('id', Enrollment::query()->where('course_id', $announcement->course_id)->select('user_id'))->get()
            : User::query()->get();

        $this->send($recipients->reject(fn (User $u) => $u->id === $announcement->user_id), new Notice(
            'announcement',
            $announcement->title,
            $announcement->course->name ?? __('announcements.global'),
            route('announcements.index'),
        ));
    }

    public function assignmentGraded(Assignment $assignment, User $student, float $score): void
    {
        $this->send(collect([$student]), new Notice(
            'grade',
            __('notifications.grade.assignment', ['title' => $assignment->title]),
            __('notifications.grade.score', ['score' => $this->number($score), 'max' => $assignment->max_score]),
            route('assignments.show', ['course' => $assignment->course_id, 'assignment' => $assignment->id]),
        ));
    }

    public function quizGraded(QuizAttempt $attempt): void
    {
        $attempt->loadMissing('quiz', 'user');

        $this->send(collect([$attempt->user]), new Notice(
            'grade',
            __('notifications.grade.quiz', ['title' => $attempt->quiz->title]),
            __('notifications.grade.score', ['score' => $this->number((float) $attempt->score), 'max' => $this->number($attempt->max_score)]),
            route('quizzes.review', ['course' => $attempt->quiz->course_id, 'quiz' => $attempt->quiz_id, 'attempt' => $attempt->id]),
        ));
    }

    /**
     * @param  Collection<int, User>  $students
     */
    public function dueSoonAssignment(Assignment $assignment, Collection $students): void
    {
        $this->send($students, new Notice(
            'reminder',
            __('notifications.reminder.assignment', ['title' => $assignment->title]),
            __('notifications.reminder.due', ['time' => $assignment->due_at?->timezone(config('app.timezone'))->translatedFormat('j M, H:i')]),
            route('assignments.show', ['course' => $assignment->course_id, 'assignment' => $assignment->id]),
        ));
    }

    /**
     * @param  Collection<int, User>  $students
     */
    public function dueSoonQuiz(Quiz $quiz, Collection $students): void
    {
        $this->send($students, new Notice(
            'reminder',
            __('notifications.reminder.quiz', ['title' => $quiz->title]),
            __('notifications.reminder.closes', ['time' => $quiz->closes_at?->timezone(config('app.timezone'))->translatedFormat('j M, H:i')]),
            route('quizzes.show', ['course' => $quiz->course_id, 'quiz' => $quiz->id]),
        ));
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function send(Collection $recipients, Notice $notice): void
    {
        if ($recipients->isNotEmpty()) {
            Notification::send($recipients->values(), $notice);
        }
    }

    private function dueText(string $courseName, ?DateTimeInterface $at, string $key): string
    {
        if (! $at) {
            return $courseName;
        }

        return $courseName.' · '.__($key, ['time' => Carbon::instance($at)->timezone(config('app.timezone'))->translatedFormat('j M, H:i')]);
    }

    private function number(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }
}
