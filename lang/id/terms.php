<?php

/*
 * Istilah yang menyesuaikan jenjang institusi (INSTITUTION_LEVEL): `school` (SMP/SMA/SMK) atau `campus`.
 * Pakai helper term('teacher') atau App\Support\Terminology.
 */
return [
    'school' => [
        'teacher' => 'Guru',
        'teachers' => 'Guru',
        'student' => 'Siswa',
        'students' => 'Siswa',
        'course' => 'Mata Pelajaran',
        'class' => 'Kelas',
        'section' => 'Topik',
        'sections' => 'Topik',
        'homeroom' => 'Wali Kelas',
        'principal' => 'Kepala Sekolah',
        'identity' => 'NIS',
        'institution' => 'Sekolah',
    ],
    'campus' => [
        'teacher' => 'Dosen',
        'teachers' => 'Dosen',
        'student' => 'Mahasiswa',
        'students' => 'Mahasiswa',
        'course' => 'Mata Kuliah',
        'class' => 'Kelas',
        'section' => 'Pertemuan',
        'sections' => 'Pertemuan',
        'homeroom' => 'Dosen Wali',
        'principal' => 'Ketua Program Studi',
        'identity' => 'NIM',
        'institution' => 'Kampus',
    ],
];
