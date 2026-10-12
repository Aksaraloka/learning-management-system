<?php

namespace App\Console\Commands;

use App\Actions\Setup\CreateAdmin;
use App\Actions\Setup\EnsureRoles;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('aksaraloka:install {--name=Admin : Nama admin} {--email= : Email admin} {--password= : Kata sandi (acak bila kosong)}')]
#[Description('Menyiapkan peran dan membuat akun admin pertama institusi')]
class InstallCommand extends Command
{
    public function handle(EnsureRoles $roles, CreateAdmin $createAdmin): int
    {
        $roles->handle();
        $this->components->info('Peran aplikasi siap.');

        $email = (string) $this->option('email');

        if ($email === '') {
            $this->components->warn('Tidak ada --email, akun admin tidak dibuat. Contoh: php artisan aksaraloka:install --email=admin@kampus.ac.id');

            return self::SUCCESS;
        }

        if (User::where('email', $email)->exists()) {
            $this->components->error("Email {$email} sudah terdaftar.");

            return self::FAILURE;
        }

        $result = $createAdmin->handle((string) $this->option('name'), $email, $this->option('password') ?: null);

        $this->components->info('Admin dibuat.');
        $this->table(['Email', 'Kata sandi'], [[$email, $result['password']]]);

        return self::SUCCESS;
    }
}
