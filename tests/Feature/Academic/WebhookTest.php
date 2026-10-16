<?php

use App\Actions\Academic\SyncAcademicData;
use App\Jobs\ProcessAcademicWebhook;
use App\Models\AcademicTerm;
use App\Models\Course;
use App\Models\User;
use App\Models\WebhookReceipt;
use App\Support\Academic\WebhookSignature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    enableAcademic();
    Cache::flush();
});

/**
 * Kirim webhook bertanda tangan ke penerima.
 *
 * @param  array<string, mixed>  $data
 * @param  array<string, mixed>  $options  `event_id`, `timestamp`, `signature`, `secret`
 */
function postWebhook(string $type, array $data = [], array $options = [])
{
    $eventId = $options['event_id'] ?? (string) Str::uuid();
    $timestamp = (string) ($options['timestamp'] ?? now()->timestamp);
    $raw = json_encode(['id' => $eventId, 'type' => $type, 'occurred_at' => now()->toIso8601String(), 'data' => $data]);
    $signature = $options['signature'] ?? WebhookSignature::sign($timestamp, $raw, $options['secret'] ?? 'whsec');

    return test()->call('POST', route('webhooks.academic'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_AKSARALOKA_EVENT' => $type,
        'HTTP_X_AKSARALOKA_EVENT_ID' => $eventId,
        'HTTP_X_AKSARALOKA_TIMESTAMP' => $timestamp,
        'HTTP_X_AKSARALOKA_SIGNATURE' => $signature,
    ], $raw);
}

describe('signature and idempotency', function () {
    it('rejects a wrong signature', function () {
        Queue::fake();

        postWebhook('class.updated', ['class_id' => 10], ['signature' => 'sha256=salah'])->assertUnauthorized();
        postWebhook('class.updated', ['class_id' => 10], ['secret' => 'rahasia-lain'])->assertUnauthorized();

        Queue::assertNothingPushed();
        expect(WebhookReceipt::count())->toBe(0);
    });

    it('rejects a request without a configured secret', function () {
        config(['academic.webhook_secret' => '']);

        postWebhook('class.updated', ['class_id' => 10], ['secret' => ''])->assertUnauthorized();
    });

    it('rejects an expired timestamp even with a valid signature', function () {
        Queue::fake();

        postWebhook('class.updated', ['class_id' => 10], ['timestamp' => now()->subMinutes(6)->timestamp])->assertUnauthorized();
        postWebhook('class.updated', ['class_id' => 10], ['timestamp' => now()->addMinutes(6)->timestamp])->assertUnauthorized();

        Queue::assertNothingPushed();
    });

    it('accepts a timestamp inside the tolerance window', function () {
        Queue::fake();

        postWebhook('class.updated', ['class_id' => 10], ['timestamp' => now()->subMinutes(4)->timestamp])->assertOk();

        Queue::assertPushed(ProcessAcademicWebhook::class);
    });

    it('accepts an ISO 8601 timestamp', function () {
        Queue::fake();

        postWebhook('class.updated', ['class_id' => 10], ['timestamp' => now()->toIso8601String()])->assertOk();
    });

    it('answers 200 to a duplicate event without processing it again', function () {
        Queue::fake();
        $eventId = (string) Str::uuid();

        postWebhook('class.updated', ['class_id' => 10], ['event_id' => $eventId])->assertOk()->assertJson(['status' => 'accepted']);
        postWebhook('class.updated', ['class_id' => 10], ['event_id' => $eventId])->assertOk()->assertJson(['status' => 'duplicate']);

        Queue::assertPushed(ProcessAcademicWebhook::class, 1);
        expect(WebhookReceipt::where('event_id', $eventId)->count())->toBe(1);
    });

    it('records the receipt and marks it processed', function () {
        fakeAcademicSync([academicClass()], [10 => []]);
        $eventId = (string) Str::uuid();

        postWebhook('class.updated', ['class_id' => 10], ['event_id' => $eventId])->assertOk();

        $receipt = WebhookReceipt::where('event_id', $eventId)->firstOrFail();
        expect($receipt->type)->toBe('class.updated')->and($receipt->received_at)->not->toBeNull()->and($receipt->processed_at)->not->toBeNull();
    });

    it('ignores unknown event types but still answers 200', function () {
        postWebhook('sesuatu.baru', ['x' => 1])->assertOk();

        expect(WebhookReceipt::firstOrFail()->processed_at)->not->toBeNull();
    });

    it('is not available when the integration is off', function () {
        config(['academic.enabled' => false]);

        postWebhook('class.updated')->assertNotFound();
    });
});

describe('events', function () {
    beforeEach(function () {
        fakeAcademicSync([academicClass()], [10 => [academicStudent(1), academicStudent(2)]]);
        app(SyncAcademicData::class)->handle();

        $this->course = Course::firstOrFail();
        $this->student = User::where('academic_user_id', 1)->firstOrFail();
    });

    it('class.updated refreshes the class', function () {
        fakeAcademicSync([academicClass(10, ['class_label' => 'B', 'subject' => ['code' => 'IF101', 'name' => 'Basis Data Lanjut', 'credits' => 4]])], [10 => [academicStudent(1), academicStudent(2)]]);

        postWebhook('class.updated', ['class_id' => 10])->assertOk();

        $this->course->refresh();
        expect($this->course->name)->toBe('Basis Data Lanjut B')->and($this->course->credits)->toBe(4);
    });

    it('roster.updated adds and removes participants', function () {
        fakeAcademicSync([academicClass()], [10 => [academicStudent(2), academicStudent(3)]]);

        postWebhook('roster.updated', ['class_id' => 10])->assertOk();

        $ids = $this->course->students()->pluck('users.academic_user_id')->sort()->values()->all();
        expect($ids)->toBe([2, 3])->and($this->course->hasMember($this->student))->toBeFalse();
    });

    it('roster.updated creates a class that Learn does not know yet', function () {
        fakeAcademicSync([academicClass(), academicClass(11, ['subject' => ['code' => 'IF300', 'name' => 'Baru', 'credits' => 2]])], [11 => [academicStudent(5)]]);

        postWebhook('roster.updated', ['class_id' => 11])->assertOk();

        expect(Course::where('academic_class_id', 11)->exists())->toBeTrue();
    });

    it('class.updated hides a class that no longer exists in Academic', function () {
        fakeAcademicSync([], []);

        postWebhook('class.updated', ['class_id' => 10])->assertOk();

        expect(Course::count())->toBe(0)->and(Course::withTrashed()->count())->toBe(1);
    });

    it('class.deleted hides the course without deleting it permanently', function () {
        postWebhook('class.deleted', ['class_id' => 10])->assertOk();

        expect(Course::count())->toBe(0)
            ->and(Course::withTrashed()->where('academic_class_id', 10)->firstOrFail()->trashed())->toBeTrue();
    });

    it('term.updated refreshes the term', function () {
        fakeAcademicSync([academicClass()], [], ['is_locked' => true, 'name' => '2026/2027 Ganjil']);

        postWebhook('term.updated', ['term_id' => 1])->assertOk();

        expect(AcademicTerm::firstOrFail()->is_locked)->toBeTrue();
    });

    it('user.updated refreshes the person through their classes', function () {
        fakeAcademicSync([academicClass()], [10 => [academicStudent(1, ['name' => 'Nama Baru']), academicStudent(2)]]);

        postWebhook('user.updated', ['user_id' => 1])->assertOk();

        expect($this->student->fresh()->name)->toBe('Nama Baru');
    });

    it('user.updated for an unknown person does nothing', function () {
        postWebhook('user.updated', ['user_id' => 99999])->assertOk();

        expect(User::where('academic_user_id', 99999)->exists())->toBeFalse();
    });

    it('student.status_changed to a disallowed status logs the user out everywhere but keeps their data', function (string $status) {
        DB::table('sessions')->insert([
            ['id' => 's1', 'user_id' => $this->student->id, 'payload' => 'x', 'last_activity' => time()],
            ['id' => 's2', 'user_id' => $this->student->id, 'payload' => 'x', 'last_activity' => time()],
            ['id' => 's3', 'user_id' => User::where('academic_user_id', 2)->value('id'), 'payload' => 'x', 'last_activity' => time()],
        ]);
        $token = $this->student->remember_token;

        postWebhook('student.status_changed', ['user_id' => 1, 'status' => $status])->assertOk();

        expect(DB::table('sessions')->where('user_id', $this->student->id)->count())->toBe(0)
            ->and(DB::table('sessions')->count())->toBe(1)
            ->and($this->student->fresh()->remember_token)->not->toBe($token)
            ->and(User::find($this->student->id))->not->toBeNull()
            ->and($this->course->hasMember($this->student))->toBeTrue();
    })->with(['nonaktif', 'mutasi', 'lulus', 'keluar']);

    it('student.status_changed to an allowed status keeps the sessions', function () {
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $this->student->id, 'payload' => 'x', 'last_activity' => time()]);

        postWebhook('student.status_changed', ['user_id' => 1, 'status' => 'cuti'])->assertOk();

        expect(DB::table('sessions')->where('user_id', $this->student->id)->count())->toBe(1);
    });

    it('grades.locked locks the grade categories of that class only', function () {
        fakeAcademicSync([academicClass(), academicClass(11, ['subject' => ['code' => 'IF300', 'name' => 'Lain', 'credits' => 2]])], []);
        app(SyncAcademicData::class)->handle();

        postWebhook('grades.locked', ['class_id' => 10])->assertOk();

        expect(Course::where('academic_class_id', 10)->firstOrFail()->isGradesLocked())->toBeTrue()
            ->and(Course::where('academic_class_id', 11)->firstOrFail()->isGradesLocked())->toBeFalse();

        $this->actingAs(User::where('academic_user_id', 900)->firstOrFail())
            ->get(route('gradebook.index', $this->course))->assertOk();
    });
});
