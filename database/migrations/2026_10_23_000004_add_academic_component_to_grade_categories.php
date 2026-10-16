<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_categories', function (Blueprint $table) {
            $table->string('academic_component', 20)->nullable()->after('weight'); // assignment | midterm | final
        });

        // Kategori bawaan: Tugas dan Kuis dikirim sebagai komponen tugas; Ujian dibiarkan kosong.
        DB::table('grade_categories')->whereIn('name', ['Tugas', 'Kuis'])->update(['academic_component' => 'assignment']);
    }

    public function down(): void
    {
        Schema::table('grade_categories', fn (Blueprint $table) => $table->dropColumn('academic_component'));
    }
};
