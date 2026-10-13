<?php

use App\Actions\Gradebook\UpdateGradeCategories;
use App\Actions\Quizzes\FinishAttempt;
use App\Actions\Quizzes\SaveAttemptAnswer;
use App\Actions\Quizzes\StartAttempt;
use App\Enums\QuestionType;
use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Exports\GradebookExport;
use App\Livewire\Gradebook\Index;
use App\Models\AcademicTerm;
use App\Models\Submission;
use App\Support\Gradebook;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->section = makeSection($this->course);
    $this->tugas = $this->course->gradeCategories()->create(['name' => 'Tugas', 'weight' => 40, 'position' => 1]);
    $this->kuis = $this->course->gradeCategories()->create(['name' => 'Kuis', 'weight' => 30, 'position' => 2]);
    $this->ujian = $this->course->gradeCategories()->create(['name' => 'Ujian', 'weight' => 30, 'position' => 3]);
    $this->ani = makeUser(Role::Student, ['name' => 'Ani']);
    $this->budi = makeUser(Role::Student, ['name' => 'Budi']);
    enroll($this->course, $this->ani);
    enroll($this->course, $this->budi);
});

function grade(object $t, $assignment, $student, float $score): void
{
    Submission::create(['assignment_id' => $assignment->id, 'user_id' => $student->id, 'status' => SubmissionStatus::Graded, 'score' => $score, 'graded_at' => now(), 'body' => 'x']);
}

function row(object $t, $student): array
{
    return Gradebook::compute($t->course->fresh())['rows'][$student->id];
}

it('computes category averages and a weighted final grade', function () {
    $a1 = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'max_score' => 100, 'due_at' => now()->subDay()]);
    $a2 = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'max_score' => 50, 'due_at' => now()->subDay(), 'position' => 2]);
    grade($this, $a1, $this->ani, 80);   // 80%
    grade($this, $a2, $this->ani, 25);   // 50% -> rata-rata tugas 65

    makeQuiz($this->course, ['grade_category_id' => $this->kuis->id, 'closes_at' => now()->subHour()]);
    $row = row($this, $this->ani);

    // Kategori Kuis belum punya nilai bagi Ani yang tidak mengerjakan? Kuis sudah ditutup => 0%.
    expect($row['categories'][$this->tugas->id])->toBe(65.0)
        ->and($row['categories'][$this->kuis->id])->toBe(0.0)
        ->and($row['categories'][$this->ujian->id])->toBeNull()
        // (65*40 + 0*30) / 70
        ->and($row['final'])->toBe(round(65 * 40 / 70, 2));

});

it('normalizes weights so empty categories do not drag the grade down', function () {
    $a = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'due_at' => now()->subDay()]);
    grade($this, $a, $this->ani, 90);

    expect(row($this, $this->ani)['final'])->toBe(90.0);
});

it('counts a missing submission as zero only after the due date', function () {
    $future = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'due_at' => now()->addDay()]);
    expect(row($this, $this->budi)['items']['a'.$future->id])->toBe(['score' => null, 'percent' => null, 'pending' => false])
        ->and(row($this, $this->budi)['final'])->toBeNull();

    $future->update(['due_at' => now()->subHour()]);

    expect(row($this, $this->budi)['items']['a'.$future->id]['percent'])->toBe(0.0)->and(row($this, $this->budi)['final'])->toBe(0.0);
});

it('does not count submissions waiting to be graded, even after the due date', function () {
    $a = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'due_at' => now()->subDay()]);
    Submission::create(['assignment_id' => $a->id, 'user_id' => $this->ani->id, 'body' => 'x', 'status' => SubmissionStatus::Submitted, 'submitted_at' => now()->subDays(2)]);

    $cell = row($this, $this->ani)['items']['a'.$a->id];

    expect($cell['pending'])->toBeTrue()->and($cell['percent'])->toBeNull()->and(row($this, $this->ani)['final'])->toBeNull();
});

it('ignores items without a category in the final grade but still lists them', function () {
    $a = makeAssignment($this->course, ['grade_category_id' => null, 'due_at' => now()->subDay()]);
    grade($this, $a, $this->ani, 100);

    $data = Gradebook::compute($this->course);

    expect($data['rows'][$this->ani->id]['final'])->toBeNull()->and($data['items'])->toHaveCount(1);
});

it('uses the best graded quiz attempt', function () {
    $quiz = makeQuiz($this->course, ['grade_category_id' => $this->kuis->id, 'max_attempts' => 2]);
    $tf = addQuestion($quiz, QuestionType::TrueFalse, ['points' => 10]);

    foreach (['false', 'true'] as $answer) {
        $attempt = app(StartAttempt::class)->handle($quiz->fresh(), $this->ani);
        app(SaveAttemptAnswer::class)->handle($attempt, $tf->id, $answer);
        app(FinishAttempt::class)->handle($attempt);
    }

    expect(row($this, $this->ani)['categories'][$this->kuis->id])->toBe(100.0);
});

it('does not count a quiz attempt that waits for essay grading, and ignores draft quizzes', function () {
    $quiz = makeQuiz($this->course, ['grade_category_id' => $this->kuis->id, 'closes_at' => now()->addHour()]);
    addQuestion($quiz, QuestionType::Essay, ['points' => 5]);
    $attempt = app(StartAttempt::class)->handle($quiz->fresh(), $this->ani);
    app(FinishAttempt::class)->handle($attempt);
    makeQuiz($this->course, ['title' => 'Draf', 'is_published' => false, 'grade_category_id' => $this->kuis->id, 'closes_at' => now()->subDay(), 'position' => 2]);

    $data = Gradebook::compute($this->course);

    expect($data['rows'][$this->ani->id]['items']['q'.$quiz->id]['pending'])->toBeTrue()
        ->and($data['items'])->toHaveCount(1)
        ->and($data['rows'][$this->ani->id]['final'])->toBeNull();
});

it('does not count teachers as students', function () {
    expect(Gradebook::compute($this->course)['students']->pluck('id')->all())->toEqualCanonicalizing([$this->ani->id, $this->budi->id]);
});

it('updates category weights when they add up to 100', function () {
    app(UpdateGradeCategories::class)->handle($this->course, [
        $this->tugas->id => ['name' => 'Tugas Harian', 'weight' => '50'],
        $this->kuis->id => ['name' => 'Kuis', 'weight' => '20'],
        $this->ujian->id => ['name' => 'Ujian', 'weight' => '30'],
    ]);

    expect($this->tugas->fresh()->name)->toBe('Tugas Harian')->and($this->tugas->fresh()->weight)->toBe(50);
});

it('rejects weights that do not total 100, are negative, fractional or foreign', function (array $weights, string $key) {
    $entries = [
        $this->tugas->id => ['name' => 'Tugas', 'weight' => $weights[0]],
        $this->kuis->id => ['name' => 'Kuis', 'weight' => $weights[1]],
        $this->ujian->id => ['name' => 'Ujian', 'weight' => $weights[2]],
    ];

    expect(fn () => app(UpdateGradeCategories::class)->handle($this->course, $entries))->toThrow(ValidationException::class);
    expect($this->tugas->fresh()->weight)->toBe(40);
})->with([
    'total 90' => [['40', '30', '20'], 'weights'],
    'negative' => [['-10', '80', '30'], 'weight'],
    'fractional' => [['40.5', '29.5', '30'], 'weight'],
    'text' => [['abc', '40', '60'], 'weight'],
]);

it('adds and removes unused categories', function () {
    $update = app(UpdateGradeCategories::class);
    $new = $update->add($this->course, 'Praktikum');

    expect($new->weight)->toBe(0)->and(fn () => $update->add($this->course, 'Praktikum'))->toThrow(ValidationException::class);

    makeAssignment($this->course, ['grade_category_id' => $this->tugas->id]);
    expect(fn () => $update->remove($this->course, $this->tugas))->toThrow(ValidationException::class);

    $update->remove($this->course, $new);
    expect($this->course->gradeCategories()->where('name', 'Praktikum')->exists())->toBeFalse();
});

it('cannot remove a category from another course', function () {
    $foreign = makeCourse(makeUser(Role::Teacher))->gradeCategories()->create(['name' => 'Asing', 'weight' => 100, 'position' => 1]);

    expect(fn () => app(UpdateGradeCategories::class)->remove($this->course, $foreign))->toThrow(NotFoundHttpException::class);
});

it('blocks weight changes when the term is locked', function () {
    AcademicTerm::query()->update(['is_locked' => true]);

    expect(fn () => app(UpdateGradeCategories::class)->handle($this->course->fresh(), []))->toThrow(AuthorizationException::class);

    Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])->call('saveWeights')->assertForbidden();
});

it('lets the teacher save weights from the page', function () {
    Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])
        ->set("entries.{$this->tugas->id}.weight", '60')->set("entries.{$this->kuis->id}.weight", '20')->set("entries.{$this->ujian->id}.weight", '20')
        ->call('saveWeights')->assertHasNoErrors();

    expect($this->tugas->fresh()->weight)->toBe(60);

    Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])
        ->set("entries.{$this->tugas->id}.weight", '10')->call('saveWeights')->assertHasErrors(['weights']);
});

it('does not let students or the principal change weights', function () {
    foreach ([$this->ani, makeUser(Role::Principal)] as $user) {
        Livewire::actingAs($user)->test(Index::class, ['course' => $this->course])->call('saveWeights')->assertForbidden();
        Livewire::actingAs($user)->test(Index::class, ['course' => $this->course])->call('addCategory')->assertForbidden();
    }
});

it('shows the teacher and principal everyone, but a student only their own row', function () {
    $a = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'due_at' => now()->subDay(), 'title' => 'Tugas Rahasia']);
    grade($this, $a, $this->ani, 77);
    grade($this, $a, $this->budi, 58.25);

    Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])->assertSee('Ani')->assertSee('Budi');
    Livewire::actingAs(makeUser(Role::Principal))->test(Index::class, ['course' => $this->course])->assertSee('Ani')->assertSee('Budi');

    $html = Livewire::actingAs($this->ani)->test(Index::class, ['course' => $this->course])->assertSee('Nilai saya')->assertSee('77')->html();
    expect($html)->not->toContain('Budi')->not->toContain('58,25');
});

it('keeps non members out of the gradebook', function () {
    $outsider = makeUser(Role::Student);

    $this->actingAs($outsider)->get(route('gradebook.index', $this->course))->assertForbidden();
    $this->actingAs($outsider)->get(route('gradebook.export', $this->course))->assertForbidden();
    $this->actingAs($this->ani)->get(route('gradebook.export', $this->course))->assertForbidden();
    $this->actingAs(makeUser(Role::Teacher))->get(route('gradebook.export', $this->course))->assertForbidden();
});

it('exports an xlsx for the teacher and principal', function () {
    Excel::fake();

    $this->actingAs($this->teacher)->get(route('gradebook.export', $this->course))->assertOk();
    $this->actingAs(makeUser(Role::Principal))->get(route('gradebook.export', $this->course))->assertOk();

    Excel::assertDownloaded('nilai-'.Str::slug($this->course->name).'.xlsx', fn (GradebookExport $e) => true);
});

it('builds export rows with headers, raw scores, categories and final grade', function () {
    $a = makeAssignment($this->course, ['grade_category_id' => $this->tugas->id, 'due_at' => now()->subDay(), 'title' => 'Latihan 1', 'max_score' => 50]);
    grade($this, $a, $this->ani, 40);

    $rows = (new GradebookExport($this->course))->array();

    expect($rows[0])->toBe(['NIM', 'Mahasiswa', 'Latihan 1 (/50)', 'Tugas (%40)', 'Kuis (%30)', 'Ujian (%30)', 'Nilai akhir', 'Kehadiran (%)'])
        ->and($rows[1][1])->toBe('Ani')
        ->and($rows[1][2])->toBe(40.0)
        ->and($rows[1][3])->toBe(80.0)
        ->and($rows[1][6])->toBe(80.0)
        ->and($rows[2][2])->toBe(0.0);
});

it('neutralizes spreadsheet formulas in exported text', function () {
    expect(GradebookExport::safe('=HYPERLINK("x")'))->toBe("'=HYPERLINK(\"x\")")
        ->and(GradebookExport::safe('+1'))->toBe("'+1")
        ->and(GradebookExport::safe('Normal'))->toBe('Normal');
});
