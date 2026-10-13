<?php

namespace App\Console\Commands;

use App\Actions\Notifications\Notifier;
use App\Enums\SubmissionStatus;
use App\Models\Assignment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:due')]
#[Description('Mengirim pengingat H-1 untuk tugas dan kuis yang akan berakhir dalam 24 jam')]
class SendDueRemindersCommand extends Command
{
    public const WINDOW_HOURS = 24;

    public function handle(Notifier $notifier): int
    {
        $limit = now()->addHours(self::WINDOW_HOURS);
        $sent = 0;

        // Tugas: hanya mahasiswa yang belum mengumpulkan (kiriman dikembalikan tetap diingatkan).
        Assignment::query()->with('course')->whereNull('due_reminded_at')
            ->where('due_at', '>', now())->where('due_at', '<=', $limit)
            ->each(function (Assignment $assignment) use ($notifier, &$sent) {
                $done = Submission::query()->where('assignment_id', $assignment->id)
                    ->whereIn('status', [SubmissionStatus::Submitted->value, SubmissionStatus::Late->value, SubmissionStatus::Graded->value])
                    ->select('user_id');

                $students = $assignment->course->students()->whereNotIn('users.id', $done)->get();
                $notifier->dueSoonAssignment($assignment, $students);
                $assignment->forceFill(['due_reminded_at' => now()])->saveQuietly();
                $sent += $students->count();
            });

        // Kuis: hanya mahasiswa yang belum pernah mencoba.
        Quiz::query()->with('course')->where('is_published', true)->whereNull('due_reminded_at')
            ->where('closes_at', '>', now())->where('closes_at', '<=', $limit)
            ->each(function (Quiz $quiz) use ($notifier, &$sent) {
                $tried = QuizAttempt::query()->where('quiz_id', $quiz->id)->select('user_id');

                $students = $quiz->course->students()->whereNotIn('users.id', $tried)->get();
                $notifier->dueSoonQuiz($quiz, $students);
                $quiz->forceFill(['due_reminded_at' => now()])->saveQuietly();
                $sent += $students->count();
            });

        $this->components->info("{$sent} pengingat dikirim.");

        return self::SUCCESS;
    }
}
