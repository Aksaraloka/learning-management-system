<?php

namespace App\Console\Commands;

use App\Actions\Quizzes\FinishAttempt;
use App\Enums\AttemptStatus;
use App\Models\QuizAttempt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('quizzes:finalize-expired')]
#[Description('Menyelesaikan percobaan kuis yang waktunya habis (mis. peramban ditutup sebelum menekan Selesai)')]
class FinalizeExpiredAttemptsCommand extends Command
{
    public function handle(FinishAttempt $finish): int
    {
        $limit = now()->subSeconds(QuizAttempt::GRACE_SECONDS);
        $done = 0;

        QuizAttempt::query()
            ->where('status', AttemptStatus::InProgress->value)
            ->whereNotNull('deadline_at')->where('deadline_at', '<', $limit)
            ->chunkById(200, function ($attempts) use ($finish, &$done) {
                foreach ($attempts as $attempt) {
                    $finish->handle($attempt);
                    $done++;
                }
            });

        $this->components->info("{$done} percobaan diselesaikan.");

        return self::SUCCESS;
    }
}
