<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_terms', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_term_id')->nullable();
            $table->unique('academic_term_id', 'academic_terms_academic_term_id_unique');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_class_id')->nullable();
            $table->boolean('grades_locked')->default(false);
            $table->unique('academic_class_id', 'courses_academic_class_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique('courses_academic_class_id_unique');
            $table->dropColumn(['academic_class_id', 'grades_locked']);
        });

        Schema::table('academic_terms', function (Blueprint $table) {
            $table->dropUnique('academic_terms_academic_term_id_unique');
            $table->dropColumn('academic_term_id');
        });
    }
};
