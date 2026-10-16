<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64);
            $table->string('type', 60);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();

            $table->unique('event_id', 'webhook_receipts_event_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
    }
};
