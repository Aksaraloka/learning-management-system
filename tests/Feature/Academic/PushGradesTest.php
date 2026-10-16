<?php

use App\Actions\Academic\SyncAcademicData;
use App\Actions\Courses\CreateCourse;
use App\Actions\Gradebook\PushGradesToAcademic;
use App\Enums\AcademicGradeComponent;
use App\Enums\Role;
use App\Livewire\Gradebook\Index;
use App\Models\AcademicTerm;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Submission;
use App\Models\User;
use App\Support\Academic\AcademicException;
use App\Support\Gradebook;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    enableAcademic();
    Cache::flush();

    fakeAcademicSync([academicClass()], [10 => [academicStudent(1), academicStudent(2), academicStudent(3)]]);
    app(SyncAcademicData::class)->handle();

    $this->course = Course::firstOrFail();
    $this->teacher = User::where('academic_user_id', 900)->firstOrFail();
    $this->s1 = User::where('academic_user_id', 1)->firstOrFail();
    $this->s2 = User::where('academic_user_id', 2)->firstOrFail();
    $this->s3 = User::where('academic_user_id', 3)->firstOrFail();

    $this->tugas = $this->course->gradeCategories()->where('name', 'Tugas')->firstOrFail();
    $this->kuis = $this->course->gradeCategories()->where('name', 'Kuis')->firstOrFail();
    $this->ujian = $this->course->gradeCategories()->where('name', 'Ujian')->firstOrFail();
});

function gradeOn(Course $course, $category, array $scores, string $title = 'Tugas 1'): void
{
    $assignment = makeAssignment($course, ['title' => $title, 'grade_category_id' => $category->id, 'due_at' => now()->addDay()]);

    foreach ($scores as $userId => $score) {
        Submission::create($score === null
            ? ['assignment_id' => $assignment->id, 'user_id' => $userId, 'status' => 'submitted']
            : ['assignment_id' => $assignment->id, 'user_id' => $userId, 'status' => 'graded', 'score' => $score]);
    }
}

function fakePush(int $status = 200, array $body = ['updated' => 2, 'skipped' => []]): void
{
    Http::swap(new Factory);
    Http::fake(['academic.test/api/integration/v1/classes/*/grades' => Http::response($body, $status)]);
}

describe('defaults and mapping', function () {
    it('maps the default categories: Tugas and Kuis to assignment, Ujian to nothing', function () {
        expect($this->tugas->academic_component)->toBe(AcademicGradeComponent::Assignment)
            ->and($this->kuis->academic_component)->toBe(AcademicGradeComponent::Assignment)
            ->and($this->ujian->academic_component)->toBeNull();

        $manual = app(CreateCourse::class)->handle(['academic_term_id' => AcademicTerm::firstOrFail()->id, 'name' => 'Manual', 'credits' => 2], makeUser(Role::Admin));

        expect($manual->gradeCategories->map(fn ($c) => $c->academic_component?->value)->all())->toBe(['assignment', 'assignment', null]);
    });

    it('lets the lecturer map a category to a component and audits it', function () {
        $component = Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course]);

        $component->set("entries.{$this->ujian->id}.component", 'midterm')->call('saveWeights')->assertHasNoErrors();

        expect($this->ujian->fresh()->academic_component)->toBe(AcademicGradeComponent::Midterm)
            ->and(AuditLog::where('action', 'gradebook.components_updated')->count())->toBe(1);
    });

    it('rejects an unknown component', function () {
        Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])
            ->set("entries.{$this->ujian->id}.component", 'bonus')->call('saveWeights')
            ->assertHasErrors("entries.{$this->ujian->id}.component");
    });

    it('clears a mapping when the empty option is chosen', function () {
        Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])
            ->set("entries.{$this->kuis->id}.component", '')->call('saveWeights')->assertHasNoErrors();

        expect($this->kuis->fresh()->academic_component)->toBeNull();
    });
});

describe('component scores', function () {
    it('is the weighted average of the mapped categories with the same normalisation as the final grade', function () {
        gradeOn($this->course, $this->tugas, [$this->s1->id => 80, $this->s2->id => 60, $this->s3->id => null]);
        gradeOn($this->course, $this->kuis, [$this->s1->id => 60], 'Kuis 1');
        $this->ujian->update(['academic_component' => AcademicGradeComponent::Midterm]);
        gradeOn($this->course, $this->ujian, [$this->s1->id => 90], 'UTS');

        $assignment = Gradebook::componentScores($this->course, AcademicGradeComponent::Assignment);
        $midterm = Gradebook::componentScores($this->course, AcademicGradeComponent::Midterm);
        $final = Gradebook::componentScores($this->course, AcademicGradeComponent::Final);

        // s1: (80*40 + 60*30) / 70; s2: hanya Tugas (Kuis kosong tidak menarik ke bawah); s3: menunggu.
        expect($assignment[$this->s1->id]['score'])->toBe(71.43)
            ->and($assignment[$this->s2->id]['score'])->toBe(60.0)
            ->and($assignment[$this->s3->id])->toBe(['score' => null, 'waiting' => true])
            ->and($assignment[$this->s1->id]['waiting'])->toBeFalse()
            ->and($midterm[$this->s1->id]['score'])->toBe(90.0)
            ->and($midterm[$this->s2->id]['waiting'])->toBeTrue()
            ->and($final[$this->s1->id]['score'])->toBeNull();
    });
});

describe('pushing', function () {
    beforeEach(function () {
        gradeOn($this->course, $this->tugas, [$this->s1->id => 80.5, $this->s2->id => 60, $this->s3->id => null]);
    });

    it('sends the component scores of students that have one, as the lecturer', function () {
        fakePush();

        $result = app(PushGradesToAcademic::class)->handle($this->course, AcademicGradeComponent::Assignment, $this->teacher);

        expect($result)->toMatchArray(['sent' => 2, 'updated' => 2, 'waiting' => 1, 'skipped' => []]);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/classes/10/grades')
            && $r['component'] === 'assignment'
            && $r['submitted_by'] === 900
            && $r['scores'] === [['user_id' => 1, 'score' => 80.5], ['user_id' => 2, 'score' => 60.0]]);
    });

    it('records an audit entry with the counts', function () {
        fakePush(body: ['updated' => 1, 'skipped' => [['user_id' => 2, 'reason' => 'bukan peserta']]]);

        $result = app(PushGradesToAcademic::class)->handle($this->course, AcademicGradeComponent::Assignment, $this->teacher);

        $log = AuditLog::where('action', 'gradebook.pushed')->firstOrFail();
        expect($log->user_id)->toBe($this->teacher->id)
            ->and($log->course_id)->toBe($this->course->id)
            ->and($log->meta)->toMatchArray(['component' => 'assignment', 'updated' => 1, 'skipped' => 1, 'waiting' => 1])
            ->and($result['skipped'][0])->toMatchArray(['user_id' => 2, 'name' => $this->s2->name, 'reason' => 'bukan peserta']);
    });

    it('does not call Academic when no student has a score yet', function () {
        fakePush();

        $result = app(PushGradesToAcademic::class)->handle($this->course, AcademicGradeComponent::Final, $this->teacher);

        expect($result['sent'])->toBe(0);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/grades'));
        expect(AuditLog::where('action', 'gradebook.pushed')->count())->toBe(0);
    });

    it('shows the Academic message for a 409 and records nothing', function () {
        fakePush(409, ['message' => 'Nilai sudah dikunci.']);

        $component = Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])->call('pushGrades', 'assignment');

        $component->assertSet('pushError', __('gradebook.academic.failed', ['message' => 'Nilai sudah dikunci.']))
            ->assertSee('Nilai sudah dikunci.');
        expect(AuditLog::where('action', 'gradebook.pushed')->count())->toBe(0);
    });

    it('shows the result and the skipped students in the page', function () {
        fakePush(body: ['updated' => 1, 'skipped' => [['user_id' => 2, 'reason' => 'bukan peserta']]]);

        Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])->call('pushGrades', 'assignment')
            ->assertSet('pushError', '')
            ->assertSee($this->s2->name)->assertSee('bukan peserta')
            ->assertSee(__('gradebook.academic.sent', ['updated' => 1, 'component' => 'Tugas']));
    });

    it('warns about students waiting before sending', function () {
        $this->actingAs($this->teacher)->get(route('gradebook.index', $this->course))
            ->assertOk()
            ->assertSee(__('gradebook.academic.send'))
            ->assertSee(__('gradebook.academic.waiting', ['n' => 1]))
            ->assertSee(__('gradebook.academic.ready', ['n' => 2]));
    });

    it('rejects an unknown component name', function () {
        Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])->call('pushGrades', 'bonus')->assertStatus(422);
    });

    it('requires an Academic-linked account', function () {
        fakePush();
        $admin = makeUser(Role::Admin);

        expect(fn () => app(PushGradesToAcademic::class)->handle($this->course, AcademicGradeComponent::Assignment, $admin))
            ->toThrow(AcademicException::class);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/grades'));
    });
});

describe('access', function () {
    it('allows only the course lecturer and admins', function () {
        $admin = makeUser(Role::Admin);
        $otherTeacher = makeUser(Role::Teacher);

        expect($this->teacher->can('pushGrades', $this->course))->toBeTrue()
            ->and($admin->can('pushGrades', $this->course))->toBeTrue()
            ->and($otherTeacher->can('pushGrades', $this->course))->toBeFalse()
            ->and($this->s1->can('pushGrades', $this->course))->toBeFalse()
            ->and(makeUser(Role::Principal)->can('pushGrades', $this->course))->toBeFalse();
    });

    it('forbids students and other lecturers from pushing', function () {
        fakePush();
        gradeOn($this->course, $this->tugas, [$this->s1->id => 80]);

        Livewire::actingAs($this->s1)->test(Index::class, ['course' => $this->course])->call('pushGrades', 'assignment')->assertForbidden();

        expect(fn () => app(PushGradesToAcademic::class)->handle($this->course, AcademicGradeComponent::Assignment, $this->s1))
            ->toThrow(AuthorizationException::class);
        expect(fn () => app(PushGradesToAcademic::class)->handle($this->course, AcademicGradeComponent::Assignment, makeUser(Role::Teacher)))
            ->toThrow(AuthorizationException::class);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/grades'));
    });

    it('is not sent when the integration is off', function () {
        fakePush();
        gradeOn($this->course, $this->tugas, [$this->s1->id => 80]);
        config(['academic.enabled' => false]);

        Livewire::actingAs($this->teacher)->test(Index::class, ['course' => $this->course])->call('pushGrades', 'assignment')->assertForbidden();

        expect(fn () => app(PushGradesToAcademic::class)->handle($this->course->fresh(), AcademicGradeComponent::Assignment, $this->teacher))
            ->toThrow(AuthorizationException::class);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/grades'));
    });

    it('is not available for courses that did not come from Academic', function () {
        $teacher = makeUser(Role::Teacher);
        $manual = makeCourse($teacher);

        expect($teacher->can('pushGrades', $manual))->toBeFalse();
        $this->actingAs($teacher)->get(route('gradebook.index', $manual))->assertOk()->assertDontSee(__('gradebook.academic.send'));
    });

    it('hides the push card from students', function () {
        gradeOn($this->course, $this->tugas, [$this->s1->id => 80]);

        $this->actingAs($this->s1)->get(route('gradebook.index', $this->course))->assertOk()->assertDontSee(__('gradebook.academic.send'));
    });
});

describe('estimated letter grades', function () {
    it('labels the letter distribution as an estimate while the integration is on', function () {
        $this->actingAs(makeUser(Role::Principal))->get(route('home'))->assertOk()->assertSee(__('dashboard.distribution_estimate'));
    });

    it('keeps the normal label when the integration is off', function () {
        config(['academic.enabled' => false]);

        $this->actingAs(makeUser(Role::Principal))->get(route('home'))->assertOk()->assertDontSee(__('dashboard.distribution_estimate'));
    });
});
