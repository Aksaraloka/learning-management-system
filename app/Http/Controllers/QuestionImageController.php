<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuizAttempt;
use App\Support\FileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionImageController extends Controller
{
    /**
     * Gambar soal: pengelola dan ketua program studi kapan saja; mahasiswa hanya yang sudah memulai kuis tersebut
     * (jadi gambar soal tidak bocor sebelum kuis dimulai).
     */
    public function __invoke(Request $request, Question $question): StreamedResponse
    {
        abort_unless($question->hasImage(), 404);

        $question->loadMissing('quiz');

        if (! Gate::allows('viewResults', $question->quiz)) {
            Gate::authorize('view', $question->quiz);

            abort_unless(
                QuizAttempt::query()->where('quiz_id', $question->quiz_id)->where('user_id', Auth::id())->exists(),
                403,
            );
        }

        return FileResponse::serve($request, $question->image_path, $question->image_name, $question->image_mime);
    }
}
