<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // mis. 2026/2027 Ganjil
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
    }
};
