<?php

namespace Database\Seeders;

use App\Actions\Setup\CreateAdmin;
use App\Actions\Setup\EnsureRoles;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\User;
use App\Support\Terminology;
use Illuminate\Database\Seeder;

/**
 * Data demo untuk satu institusi (jenjang mengikuti INSTITUTION_LEVEL): 1 admin, 1 kepala,
 * 3 guru/dosen, 30 siswa/mahasiswa, dan 1 tahun ajaran. Kelas, materi, tugas, dan kuis
 * ditambahkan seeder fase berikutnya. Kata sandi semua akun demo: "password".
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        app(EnsureRoles::class)->handle();

        $campus = Terminology::mode() === Terminology::CAMPUS;

        app(CreateAdmin::class)->handle('Admin '.institution_name(), 'admin@aksaraloka.test', self::PASSWORD);

        AcademicTerm::factory()->create(['name' => '2026/2027 Ganjil']);

        User::factory()->role(Role::Principal)->create([
            'name' => $campus ? 'Ketua Program Studi' : 'Kepala Sekolah',
            'email' => 'kepala@aksaraloka.test',
            'identity_number' => '1975010120000100',
            'password' => self::PASSWORD,
        ]);

        foreach (range(1, 3) as $i) {
            User::factory()
                ->role(...($i === 1 ? [Role::Teacher, Role::Homeroom] : [Role::Teacher]))
                ->create([
                    'email' => "guru{$i}@aksaraloka.test",
                    'identity_number' => ($campus ? '00112233' : '19800101200501').$i,
                    'password' => self::PASSWORD,
                ]);
        }

        foreach (range(1, 30) as $i) {
            User::factory()->role(Role::Student)->create([
                'email' => $i <= 5 ? "siswa{$i}@aksaraloka.test" : null, // sisanya login dengan NIS/NIM
                'identity_number' => ($campus ? '2260' : '2026').str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'password' => self::PASSWORD,
            ]);
        }
    }
}
