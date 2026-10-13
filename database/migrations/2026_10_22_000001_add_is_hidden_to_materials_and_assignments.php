<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['materials', 'assignments'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->boolean('is_hidden')->default(false));
        }
    }

    public function down(): void
    {
        foreach (['materials', 'assignments'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('is_hidden'));
        }
    }
};
