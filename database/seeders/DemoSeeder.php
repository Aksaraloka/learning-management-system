<?php

namespace Database\Seeders;

use App\Actions\Setup\CreateAdmin;
use App\Actions\Setup\EnsureRoles;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data demo satu universitas: 1 admin, 1 ketua program studi, 3 dosen (satu merangkap dosen wali),
 * 30 mahasiswa, dan 1 semester. Mata kuliah, materi, tugas, dan kuis ditambahkan seeder lain.
 * Kata sandi semua akun demo: "password".
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        app(EnsureRoles::class)->handle();

        app(CreateAdmin::class)->handle('Admin '.institution_name(), 'admin@aksaraloka.test', self::PASSWORD);

        AcademicTerm::factory()->create(['name' => '2026/2027 Ganjil']);

        User::factory()->role(Role::Principal)->create([
            'name' => 'Ketua Program Studi',
            'email' => 'kaprodi@aksaraloka.test',
            'identity_number' => '0011223300',
            'password' => self::PASSWORD,
        ]);

        foreach (range(1, 3) as $i) {
            User::factory()
                ->role(...($i === 1 ? [Role::Teacher, Role::Homeroom] : [Role::Teacher]))
                ->create([
                    'email' => "dosen{$i}@aksaraloka.test",
                    'identity_number' => '00112233'.$i,
                    'password' => self::PASSWORD,
                ]);
        }

        foreach (range(1, 30) as $i) {
            User::factory()->role(Role::Student)->create([
                'email' => $i <= 5 ? "mahasiswa{$i}@aksaraloka.test" : null, // sisanya login dengan NIM
                'identity_number' => '2260'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'password' => self::PASSWORD,
            ]);
        }
    }
}
