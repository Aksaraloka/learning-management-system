<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan: nama indeks komposit selalu eksplisit; nama otomatis yang panjang ditolak MySQL (maks. 64 karakter).
     */
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete(); // denormalisasi dari section
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('instructions')->nullable(); // HTML tersanitasi
            $table->boolean('is_published')->default(false); // draf tidak terlihat mahasiswa
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable(); // null = sampai kuis ditutup
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->timestamp('show_result_at')->nullable(); // null = langsung setelah dinilai
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'opens_at'], 'quizzes_course_opens_index');
            $table->index(['section_id', 'position'], 'quizzes_section_position_index');
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // multiple_choice | true_false | short_answer | essay
            $table->longText('body'); // HTML tersanitasi (boleh berisi rumus $...$)
            $table->json('options')->nullable(); // pilihan ganda: [{id, text}]
            $table->json('answer_key')->nullable(); // kunci; TIDAK PERNAH dikirim ke peserta
            $table->decimal('points', 6, 2)->default(1);
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('image_path')->nullable();
            $table->string('image_name')->nullable();
            $table->string('image_mime', 100)->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'position'], 'questions_quiz_position_index');
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_no');
            $table->timestamp('started_at');
            $table->timestamp('deadline_at')->nullable(); // dihitung server: min(mulai + durasi, kuis ditutup)
            $table->timestamp('finished_at')->nullable();
            $table->json('answers')->nullable(); // {question_id: jawaban}
            $table->json('flags')->nullable(); // [question_id, ...] ditandai ragu-ragu
            $table->json('question_order'); // urutan soal untuk percobaan ini
            $table->json('option_order')->nullable(); // {question_id: [option_id, ...]}
            $table->decimal('auto_score', 6, 2)->default(0);
            $table->json('manual_scores')->nullable(); // esai: {question_id: {score, feedback}}
            $table->decimal('score', 6, 2)->nullable(); // nilai akhir (null sampai selesai & esai dinilai)
            $table->decimal('max_score', 6, 2)->default(0);
            $table->string('status', 12)->default('in_progress'); // in_progress | submitted | graded
            $table->timestamps();

            $table->unique(['quiz_id', 'user_id', 'attempt_no'], 'quiz_attempts_unique_attempt');
            $table->index(['quiz_id', 'status'], 'quiz_attempts_quiz_status_index');
            $table->index(['status', 'deadline_at'], 'quiz_attempts_expiry_index');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('quizzes');
    }
};
