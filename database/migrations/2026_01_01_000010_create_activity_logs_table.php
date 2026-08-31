<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('user_name')->nullable();
            $t->string('event', 30);
            $t->string('subject_type', 80)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('description')->nullable();
            $t->decimal('montant', 14, 2)->nullable();
            $t->json('properties')->nullable();
            $t->string('ip', 45)->nullable();
            $t->dateTime('occurred_at', 3);
            $t->timestamps();
            $t->index('occurred_at');
            $t->index(['subject_type', 'subject_id']);
            $t->index(['occurred_at', 'id'], 'activity_logs_occurred_at_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
