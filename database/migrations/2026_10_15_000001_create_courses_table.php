<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 8)->unique(); // kode gabung
            $table->unsignedTinyInteger('credits')->nullable(); // SKS (kampus)
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10); // teacher | student
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title'); // topik (sekolah) atau pertemuan ke-n (kampus)
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['course_id', 'position']);
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // text | file | link | video
            $table->string('title');
            $table->longText('body')->nullable(); // HTML tersanitasi (type text)
            $table->string('url', 2048)->nullable(); // type link / video
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['section_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('courses');
    }
};
