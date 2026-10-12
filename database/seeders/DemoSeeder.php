<?php

namespace Database\Seeders;

use App\Actions\Tenants\CreateTenant;
use App\Enums\Role;
use App\Models\AcademicTerm;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\Seeder;

/**
 * Data demo: 1 super admin, tenant SMP dan tenant kampus (masing-masing 1 admin,
 * 3 guru/dosen, 30 siswa/mahasiswa, 1 tahun ajaran). Kelas, materi, tugas, dan kuis
 * ditambahkan seeder fase berikutnya. Kata sandi semua akun demo: "password".
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'super@aksaraloka.test',
            'password' => self::PASSWORD,
        ]);

        $this->seedTenant('SMP Negeri 1 Bandung', 'smpn1bandung', 'school', ['NIS' => '2026', 'staff' => 'NIP']);
        $this->seedTenant('Universitas Demo Nusantara', 'kampusdemo', 'campus', ['NIS' => '2260', 'staff' => 'NIDN']);
    }

    /**
     * @param  array{NIS: string, staff: string}  $meta
     */
    private function seedTenant(string $name, string $subdomain, string $level, array $meta): void
    {
        $result = app(CreateTenant::class)->handle(
            ['name' => $name, 'subdomain' => $subdomain, 'level' => $level],
            ['name' => 'Admin '.$name, 'email' => "admin@{$subdomain}.test", 'password' => self::PASSWORD],
        );

        Tenancy::run($result['tenant'], function () use ($subdomain, $meta) {
            AcademicTerm::factory()->create(['name' => '2026/2027 Ganjil']);

            foreach (range(1, 3) as $i) {
                User::factory()
                    ->role(...($i === 1 ? [Role::Teacher, Role::Homeroom] : [Role::Teacher]))
                    ->create([
                        'email' => "guru{$i}@{$subdomain}.test",
                        'identity_number' => $meta['staff'] === 'NIP' ? '19800101200501'.$i : '00112233'.$i,
                        'password' => self::PASSWORD,
                    ]);
            }

            User::factory()->role(Role::Principal)->create([
                'email' => "kepala@{$subdomain}.test",
                'identity_number' => '1975010120000100',
                'password' => self::PASSWORD,
            ]);

            foreach (range(1, 30) as $i) {
                User::factory()->role(Role::Student)->create([
                    'email' => $i <= 5 ? "siswa{$i}@{$subdomain}.test" : null, // sisanya login dengan NIS/NIM
                    'identity_number' => $meta['NIS'].str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'password' => self::PASSWORD,
                ]);
            }
        });
    }
}
