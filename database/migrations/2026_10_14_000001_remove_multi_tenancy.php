<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aplikasi kini melayani satu institusi: kolom/tabel tenant dihapus dan tabel peran
     * spatie dibuat ulang tanpa "teams". Aman untuk database baru (tidak ada yang diubah
     * selain kolom tenant) maupun database lama (peran & penugasan dipertahankan).
     */
    public function up(): void
    {
        // Menggabungkan beberapa tenant ambigu (NIS/email bisa bentrok) dan tidak ada cara aman
        // memilih salah satunya, jadi hentikan sebelum ada yang diubah.
        if (Schema::hasTable('tenants') && DB::table('tenants')->count() > 1) {
            throw new RuntimeException(
                'Database berisi lebih dari satu tenant. Aplikasi kini satu institusi: '
                .'hapus tenant yang tidak dipakai, atau pakai database baru (php artisan migrate:fresh --seed).'
            );
        }

        if (Schema::hasColumn('users', 'tenant_id')) {
            // Akun platform (super admin) tidak lagi dipakai.
            DB::table('users')->whereNull('tenant_id')->delete();

            Schema::table('users', function (Blueprint $table) {
                // Lepas FK lebih dulu: MySQL tidak mengizinkan menghapus indeks yang dipakai FK.
                $table->dropForeign(['tenant_id']);
                $table->dropUnique(['tenant_id', 'identity_number']);
                $table->dropColumn('tenant_id');
            });
        }

        if (! Schema::hasIndex('users', 'users_identity_number_unique')) {
            Schema::table('users', fn (Blueprint $table) => $table->unique('identity_number'));
        }

        if (Schema::hasColumn('academic_terms', 'tenant_id')) {
            Schema::table('academic_terms', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id', 'starts_at']);
                $table->dropColumn('tenant_id');
                $table->index('starts_at');
            });
        }

        if (Schema::hasColumn('import_runs', 'tenant_id')) {
            Schema::table('import_runs', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id', 'created_at']);
                $table->dropColumn('tenant_id');
                $table->index('created_at');
            });
        }

        Schema::dropIfExists('tenants');

        $this->rebuildPermissionTablesWithoutTeams();
        $this->ensureRoles();
    }

    public function down(): void
    {
        throw new RuntimeException('Migration ini tidak dapat dibatalkan (multi-tenant sudah dihapus).');
    }

    private function rebuildPermissionTablesWithoutTeams(): void
    {
        /** @var array{roles: string, permissions: string, model_has_permissions: string, model_has_roles: string, role_has_permissions: string} $t */
        $t = config('permission.table_names');

        if (! Schema::hasColumn($t['roles'], 'tenant_id')) {
            return;
        }

        $keep = array_map(fn (Role $r) => $r->value, Role::cases());

        $roles = DB::table($t['roles'])->get()->filter(fn ($r) => in_array($r->name, $keep, true));
        $permissions = DB::table($t['permissions'])->get();
        $roleHasPermissions = DB::table($t['role_has_permissions'])->get();
        $modelHasPermissions = DB::table($t['model_has_permissions'])->get();
        $modelHasRoles = DB::table($t['model_has_roles'])->get();

        Schema::disableForeignKeyConstraints();
        foreach (['model_has_permissions', 'model_has_roles', 'role_has_permissions', 'roles', 'permissions'] as $key) {
            Schema::dropIfExists($t[$key]);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create($t['permissions'], function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($t['roles'], function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($t['model_has_permissions'], function (Blueprint $table) use ($t) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->foreign('permission_id')->references('id')->on($t['permissions'])->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create($t['model_has_roles'], function (Blueprint $table) use ($t) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->foreign('role_id')->references('id')->on($t['roles'])->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create($t['role_has_permissions'], function (Blueprint $table) use ($t) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on($t['permissions'])->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on($t['roles'])->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        $now = now();

        // Satu peran per nama (sebelumnya satu salinan per tenant); petakan id lama -> id baru.
        $newIdByName = [];
        $newIdByOldId = [];

        foreach ($roles as $role) {
            $newIdByName[$role->name] ??= DB::table($t['roles'])->insertGetId([
                'name' => $role->name, 'guard_name' => $role->guard_name, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $newIdByOldId[$role->id] = $newIdByName[$role->name];
        }

        foreach ($permissions as $permission) {
            DB::table($t['permissions'])->insert((array) $permission);
        }

        $existingUsers = DB::table('users')->pluck('id')->flip();
        $seen = [];

        foreach ($modelHasRoles as $row) {
            $roleId = $newIdByOldId[$row->role_id] ?? null;
            $key = "{$roleId}|{$row->model_type}|{$row->model_id}";

            if ($roleId === null || isset($seen[$key]) || ! isset($existingUsers[$row->model_id])) {
                continue;
            }

            $seen[$key] = true;
            DB::table($t['model_has_roles'])->insert(['role_id' => $roleId, 'model_type' => $row->model_type, 'model_id' => $row->model_id]);
        }

        foreach ($roleHasPermissions as $row) {
            if (isset($newIdByOldId[$row->role_id])) {
                DB::table($t['role_has_permissions'])->insertOrIgnore(['permission_id' => $row->permission_id, 'role_id' => $newIdByOldId[$row->role_id]]);
            }
        }

        foreach ($modelHasPermissions as $row) {
            DB::table($t['model_has_permissions'])->insertOrIgnore([
                'permission_id' => $row->permission_id, 'model_type' => $row->model_type, 'model_id' => $row->model_id,
            ]);
        }
    }

    private function ensureRoles(): void
    {
        $table = config('permission.table_names.roles');
        $now = now();

        foreach (Role::cases() as $role) {
            DB::table($table)->insertOrIgnore([
                'name' => $role->value, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
};
