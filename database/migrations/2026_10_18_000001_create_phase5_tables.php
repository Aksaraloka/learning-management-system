<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_index');
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            // Null = pengumuman untuk seluruh universitas (hanya admin).
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'is_pinned', 'created_at'], 'announcements_feed_index');
        });

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->date('held_on');
            $table->string('title')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'held_on'], 'attendance_sessions_course_date_index');
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['attendance_session_id', 'user_id'], 'attendance_records_unique_student');
            $table->index(['user_id', 'status'], 'attendance_records_user_status_index');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->timestamp('due_reminded_at')->nullable();
        });

        Schema::table('quizzes', function (Blueprint $table) {
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('due_reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', fn (Blueprint $table) => $table->dropColumn(['notified_at', 'due_reminded_at']));
        Schema::table('assignments', fn (Blueprint $table) => $table->dropColumn('due_reminded_at'));
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notifications');
    }
};
