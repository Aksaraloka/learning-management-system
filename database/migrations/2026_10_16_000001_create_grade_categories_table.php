<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kategori bawaan tiap kelas; bobot dapat diubah di buku nilai (Fase 5). */
    private const DEFAULTS = [['Tugas', 40], ['Kuis', 30], ['Ujian', 30]];

    public function up(): void
    {
        Schema::create('grade_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('weight')->default(0); // persen
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'position']);
        });

        // Kelas yang sudah ada mendapat kategori bawaan.
        $now = now();

        foreach (DB::table('courses')->pluck('id') as $courseId) {
            foreach (self::DEFAULTS as $i => [$name, $weight]) {
                DB::table('grade_categories')->insert([
                    'course_id' => $courseId, 'name' => $name, 'weight' => $weight, 'position' => $i + 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_categories');
    }
};
