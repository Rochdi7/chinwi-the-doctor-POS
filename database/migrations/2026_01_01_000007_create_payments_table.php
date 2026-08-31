<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->nullable()->constrained('invoices')->cascadeOnDelete();
            $t->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('date_paiement');
            $t->decimal('montant', 12, 2);
            // Two ways to pay at the counter: cash, or the card terminal.
            // Card, cheque, transfer and traite all settle through the TPE.
            $t->enum('mode', ['especes', 'tpe'])->default('especes');
            $t->string('reference', 60)->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index('date_paiement');
            $t->index(['date_paiement', 'id'], 'payments_date_paiement_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
