<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // Pengguna dan mata kuliah boleh dihapus; jejaknya tetap ada (tanpa foreign key).
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at', 'audit_logs_created_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_index');
            $table->index(['user_id', 'created_at'], 'audit_logs_user_index');
            $table->index(['course_id', 'created_at'], 'audit_logs_course_index');
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
