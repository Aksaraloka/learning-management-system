<?php

use App\Enums\QuestionType;
use App\Enums\Role;
use App\Livewire\Quizzes\Form;
use App\Livewire\Quizzes\QuestionForm;
use App\Livewire\Quizzes\Questions;
use App\Livewire\Quizzes\Show;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->teacher = makeUser(Role::Teacher);
    $this->course = makeCourse($this->teacher);
    $this->section = makeSection($this->course);
    $this->student = makeUser(Role::Student);
    enroll($this->course, $this->student);
});

it('lets the course teacher create a quiz and continues to the question list', function () {
    Livewire::actingAs($this->teacher)->test(Form::class, ['course' => $this->course])
        ->set('section_id', (string) $this->section->id)
        ->set('title', 'Kuis Bab 1')
        ->set('instructions', '<p>Baca <script>alert(1)</script>dulu</p>')
        ->set('duration_minutes', '30')
        ->set('max_attempts', '2')
        ->set('opens_at', '2030-01-10 08:00')
        ->set('closes_at', '2030-01-10 10:00')
        ->set('shuffle_questions', true)
        ->call('save')
        ->assertHasNoErrors();

    $quiz = Quiz::firstOrFail();

    expect($quiz->title)->toBe('Kuis Bab 1')
        ->and($quiz->duration_minutes)->toBe(30)
        ->and($quiz->max_attempts)->toBe(2)
        ->and($quiz->shuffle_questions)->toBeTrue()
        ->and($quiz->is_published)->toBeFalse()
        ->and($quiz->instructions)->not->toContain('<script')
        ->and($quiz->opens_at->format('H:i'))->not->toBeNull();
});

it('validates the quiz form', function () {
    Livewire::actingAs($this->teacher)->test(Form::class, ['course' => $this->course])
        ->set('section_id', '999999')
        ->set('title', '')
        ->set('max_attempts', '0')
        ->set('opens_at', '2030-01-10 10:00')
        ->set('closes_at', '2030-01-10 08:00')
        ->call('save')
        ->assertHasErrors(['section_id', 'title', 'max_attempts', 'closes_at']);

    expect(Quiz::count())->toBe(0);
});

it('does not let students or other teachers manage a quiz', function () {
    $quiz = makeQuiz($this->course);
    $other = makeUser(Role::Teacher);

    foreach ([$this->student, $other] as $user) {
        Livewire::actingAs($user)->test(Form::class, ['course' => $this->course])->assertForbidden();
        Livewire::actingAs($user)->test(Form::class, ['course' => $this->course, 'quiz' => $quiz])->assertForbidden();
        Livewire::actingAs($user)->test(Questions::class, ['course' => $this->course, 'quiz' => $quiz])->assertForbidden();
        Livewire::actingAs($user)->test(QuestionForm::class, ['course' => $this->course, 'quiz' => $quiz])->assertForbidden();
    }
});

it('hides draft quizzes from students and from the course page', function () {
    $draft = makeQuiz($this->course, ['title' => 'Rahasia Draf', 'is_published' => false]);
    makeQuiz($this->course, ['title' => 'Kuis Terbit', 'position' => 2]);

    $this->actingAs($this->student)->get(route('quizzes.show', ['course' => $this->course, 'quiz' => $draft]))->assertForbidden();
    $this->actingAs($this->student)->get(route('courses.show', $this->course))->assertSee('Kuis Terbit')->assertDontSee('Rahasia Draf');
    $this->actingAs($this->teacher)->get(route('courses.show', $this->course))->assertSee('Kuis Terbit')->assertSee('Rahasia Draf');
});

it('returns 404 for a quiz that belongs to another course', function () {
    $quiz = makeQuiz($this->course);
    $otherCourse = makeCourse($this->teacher);

    $this->actingAs($this->teacher)->get(route('quizzes.show', ['course' => $otherCourse, 'quiz' => $quiz]))->assertNotFound();
});

it('toggles publish and soft deletes a quiz from the show page', function () {
    $quiz = makeQuiz($this->course, ['is_published' => false]);

    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course, 'quiz' => $quiz])->call('togglePublish');
    expect($quiz->fresh()->is_published)->toBeTrue();

    Livewire::actingAs($this->teacher)->test(Show::class, ['course' => $this->course, 'quiz' => $quiz->fresh()])->call('deleteQuiz');
    expect(Quiz::count())->toBe(0)->and(Quiz::withTrashed()->count())->toBe(1);
});

it('saves a multiple choice question with a stable correct option', function () {
    $quiz = makeQuiz($this->course);

    Livewire::actingAs($this->teacher)->test(QuestionForm::class, ['course' => $this->course, 'quiz' => $quiz])
        ->set('type', 'multiple_choice')
        ->set('body', '<p>Ibu kota?</p>')
        ->set('points', '3')
        ->set('options', ['Bandung', 'Jakarta', 'Surabaya'])
        ->set('correctIndex', 1)
        ->call('save')
        ->assertHasNoErrors();

    $question = Question::firstOrFail();

    expect($question->type)->toBe(QuestionType::MultipleChoice)
        ->and($question->points)->toBe(3.0)
        ->and($question->options)->toHaveCount(3)
        ->and($question->answer_key['correct'])->toBe($question->options[1]['id']);

    // Edit tanpa mengubah jumlah pilihan mempertahankan id pilihan (jawaban tersimpan tetap valid).
    $ids = array_column($question->options, 'id');

    Livewire::actingAs($this->teacher)->test(QuestionForm::class, ['course' => $this->course, 'quiz' => $quiz, 'question' => $question])
        ->set('options', ['Bandung', 'Jakarta Raya', 'Surabaya'])
        ->call('save')
        ->assertHasNoErrors();

    expect(array_column($question->fresh()->options, 'id'))->toBe($ids);
});

it('validates each question type', function () {
    $quiz = makeQuiz($this->course);
    $form = fn () => Livewire::actingAs($this->teacher)->test(QuestionForm::class, ['course' => $this->course, 'quiz' => $quiz]);

    $form()->set('body', '')->call('save')->assertHasErrors(['body']);
    $form()->set('body', '<p>x</p>')->set('options', ['Satu', ''])->call('save')->assertHasErrors(['options.1']);
    $form()->set('body', '<p>x</p>')->set('type', 'short_answer')->set('accepted', '')->call('save')->assertHasErrors(['accepted']);
    $form()->set('body', '<p>x</p>')->set('points', '0')->call('save')->assertHasErrors(['points']);

    expect(Question::count())->toBe(0);
});

it('saves true/false, short answer and essay questions with the right key', function () {
    $quiz = makeQuiz($this->course);

    Livewire::actingAs($this->teacher)->test(QuestionForm::class, ['course' => $this->course, 'quiz' => $quiz])
        ->set('type', 'true_false')->set('body', '<p>Benar?</p>')->set('trueFalse', 'false')->call('save');

    Livewire::actingAs($this->teacher)->test(QuestionForm::class, ['course' => $this->course, 'quiz' => $quiz])
        ->set('type', 'short_answer')->set('body', '<p>Isi</p>')->set('accepted', "Jakarta\n  DKI Jakarta \n")->set('caseSensitive', true)->call('save');

    Livewire::actingAs($this->teacher)->test(QuestionForm::class, ['course' => $this->course, 'quiz' => $quiz])
        ->set('type', 'essay')->set('body', '<p>Jelaskan</p>')->set('rubric', 'Poin utama')->call('save');

    [$tf, $short, $essay] = Question::orderBy('id')->get()->all();

    expect($tf->answer_key)->toBe(['correct' => false])
        ->and($short->answer_key)->toBe(['accepted' => ['Jakarta', 'DKI Jakarta'], 'case_sensitive' => true])
        ->and($essay->answer_key)->toBe(['rubric' => 'Poin utama'])
        ->and($tf->options)->toBeNull();
});

it('stores a question image on the private disk and removes it again', function () {
    Storage::fake(config('courses.disk'));
    $quiz = makeQuiz($this->course);

    Livewire::actingAs($this->teacher)->test(QuestionForm::class, ['course' => $this->course, 'quiz' => $quiz])
        ->set('body', '<p>Lihat gambar</p>')->set('options', ['A', 'B'])
        ->set('upload', UploadedFile::fake()->image('soal.png'))
        ->call('save')->assertHasNoErrors();

    $question = Question::firstOrFail();
    expect($question->hasImage())->toBeTrue();
    Storage::disk(config('courses.disk'))->assertExists($question->image_path);
    $path = $question->image_path;

    Livewire::actingAs($this->teacher)->test(QuestionForm::class, ['course' => $this->course, 'quiz' => $quiz, 'question' => $question])
        ->set('removeImage', true)->call('save')->assertHasNoErrors();

    expect($question->fresh()->hasImage())->toBeFalse();
    Storage::disk(config('courses.disk'))->assertMissing($path);
});

it('rejects a non-image question upload', function () {
    Storage::fake(config('courses.disk'));
    $quiz = makeQuiz($this->course);

    Livewire::actingAs($this->teacher)->test(QuestionForm::class, ['course' => $this->course, 'quiz' => $quiz])
        ->set('body', '<p>x</p>')->set('options', ['A', 'B'])
        ->set('upload', UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'))
        ->call('save')->assertHasErrors(['upload']);
});

it('reorders and deletes questions', function () {
    $quiz = makeQuiz($this->course);
    [$a, $b, $c] = [addQuestion($quiz), addQuestion($quiz), addQuestion($quiz)];

    Livewire::actingAs($this->teacher)->test(Questions::class, ['course' => $this->course, 'quiz' => $quiz])
        ->call('reorder', [(string) $c->id, (string) $a->id, (string) $b->id]);

    expect($quiz->questions()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);

    Livewire::actingAs($this->teacher)->test(Questions::class, ['course' => $this->course, 'quiz' => $quiz])->call('delete', $a->id);
    expect($quiz->questions()->pluck('id')->all())->toBe([$c->id, $b->id]);
});

it('cannot delete or reorder questions of another quiz', function () {
    $quiz = makeQuiz($this->course);
    $other = makeQuiz($this->course, ['title' => 'Lain', 'position' => 2]);
    $foreign = addQuestion($other);

    Livewire::actingAs($this->teacher)->test(Questions::class, ['course' => $this->course, 'quiz' => $quiz])
        ->call('delete', $foreign->id)->assertNotFound();

    expect(Question::count())->toBe(1);
});

it('computes total points and detects essays', function () {
    $quiz = makeQuiz($this->course);
    addQuestion($quiz, data: ['points' => 2.5]);
    expect($quiz->totalPoints())->toBe(2.5)->and($quiz->hasEssay())->toBeFalse();

    addQuestion($quiz, QuestionType::Essay, ['points' => 5]);
    expect($quiz->totalPoints())->toBe(7.5)->and($quiz->hasEssay())->toBeTrue();
});

it('reports the quiz state from its schedule', function () {
    expect(makeQuiz($this->course, ['is_published' => false])->state())->toBe('draft')
        ->and(makeQuiz($this->course, ['opens_at' => now()->addHour()])->state())->toBe('upcoming')
        ->and(makeQuiz($this->course, ['closes_at' => now()->subMinute()])->state())->toBe('closed')
        ->and(makeQuiz($this->course, ['opens_at' => now()->subHour(), 'closes_at' => now()->addHour()])->state())->toBe('open');
});
