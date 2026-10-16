<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('academic_user_id')->nullable()->after('identity_number');
            $table->unique('academic_user_id', 'users_academic_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_academic_user_id_unique');
            $table->dropColumn('academic_user_id');
        });
    }
};
