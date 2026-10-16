<?php

namespace App\Actions\Auth;

use App\Actions\Academic\UpsertAcademicUser;
use App\Actions\Setup\EnsureRoles;
use App\Enums\Role;
use App\Models\User;
use App\Support\Academic\AcademicStatus;
use App\Support\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Masuk dari profil `/me` Academic: cocokkan atau buat pengguna, sinkronkan peran (peran yang hilang di Academic
 * ikut dicabut), tolak mahasiswa dengan status selain aktif/cuti, lalu login. Token tidak dicatat di mana pun.
 */
class SignInFromAcademic
{
    /** Peran padanan yang boleh datang dari `/me`; `homeroom` hanya lewat penanda `is_homeroom`. */
    private const MAPPABLE = [Role::Admin, Role::Principal, Role::Teacher, Role::Student, Role::Parent];

    public function __construct(private readonly UpsertAcademicUser $upsert) {}

    /**
     * @param  array<string, mixed>  $profile  isi `/me`
     *
     * @throws AcademicSignInRejected
     */
    public function handle(array $profile): User
    {
        $role = Role::tryFrom((string) ($profile['role'] ?? ''));

        if (! $role || ! in_array($role, self::MAPPABLE, true)) {
            throw new AcademicSignInRejected(__('academic.sso.unknown_role'));
        }

        if ($role === Role::Student && ! AcademicStatus::allowsAccess($profile['status'] ?? null)) {
            throw new AcademicSignInRejected(__('academic.sso.status_rejected', ['status' => (string) ($profile['status'] ?? '-')]));
        }

        $roles = [$role];

        if (($profile['is_homeroom'] ?? false) === true) {
            $roles[] = Role::Homeroom;
        }

        $user = DB::transaction(function () use ($profile, $roles): User {
            $user = $this->upsert->handle($profile);

            if ($user->trashed()) {
                throw new AcademicSignInRejected(__('academic.sso.account_disabled'));
            }

            app(EnsureRoles::class)->handle();
            $user->syncRoles(array_map(fn (Role $r) => $r->value, $roles));

            return $user;
        });

        Auth::login($user);

        Audit::record('auth.sso_login', $user, ['roles' => implode(', ', array_map(fn (Role $r) => $r->value, $roles))], null, $user->id);

        return $user;
    }
}
