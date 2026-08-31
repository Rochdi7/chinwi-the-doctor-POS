<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caisse_mouvements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $t->enum('type', ['entree', 'sortie']);
            $t->decimal('montant', 12, 2);
            $t->decimal('solde_avant', 14, 2)->default(0);
            $t->decimal('solde_apres', 14, 2)->default(0);
            $t->string('motif');
            $t->dateTime('occurred_at', 3);
            $t->timestamps();
            $t->index('occurred_at');
            $t->index(['occurred_at', 'id'], 'caisse_mouvements_occurred_at_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caisse_mouvements');
    }
};
