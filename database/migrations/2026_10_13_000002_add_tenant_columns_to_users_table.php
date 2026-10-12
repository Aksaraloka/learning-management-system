<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null = akun platform (super admin) di domain utama.
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('identity_number', 50)->nullable()->after('email'); // NIS / NIM / NIP
            $table->string('theme', 10)->default('system')->after('password');
            $table->string('locale', 5)->default('id')->after('theme');
            $table->softDeletes();

            $table->string('email')->nullable()->change(); // siswa boleh login hanya dengan NIS
            $table->unique(['tenant_id', 'identity_number']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'identity_number']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['identity_number', 'theme', 'locale', 'deleted_at']);
        });
    }
};
