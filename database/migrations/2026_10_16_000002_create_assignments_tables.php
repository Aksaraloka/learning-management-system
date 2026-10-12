<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete(); // denormalisasi dari section agar daftar tugas murah
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->longText('instructions')->nullable(); // HTML tersanitasi
            $table->timestamp('due_at')->nullable();
            $table->unsignedSmallInteger('max_score')->default(100);
            $table->boolean('allow_late')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'due_at']);
            $table->index(['section_id', 'position']);
        });

        Schema::create('assignment_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('body')->nullable(); // HTML tersanitasi
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('status', 10)->default('submitted'); // submitted | late | graded | returned
            $table->boolean('is_late')->default(false);
            $table->decimal('score', 6, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id']);
            $table->index(['assignment_id', 'status']);
            $table->index('user_id');
        });

        Schema::create('assignment_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_private')->default(false);
            // Komentar privat adalah percakapan siswa <-> pengajar; kolom ini = siswa pemilik percakapan.
            $table->foreignId('thread_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['assignment_id', 'is_private', 'thread_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_comments');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('assignment_files');
        Schema::dropIfExists('assignments');
    }
};
