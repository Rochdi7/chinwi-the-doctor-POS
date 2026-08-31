<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->string('numero', 40)->unique();
            // A counter sale has no named buyer, so this stays nullable rather
            // than forcing a placeholder client record for every walk-in.
            $t->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('date_facture');
            $t->string('bc_client', 60)->nullable();
            $t->decimal('total_ht', 12, 2)->default(0);
            $t->decimal('total_tva', 12, 2)->default(0);
            $t->decimal('remise', 12, 2)->default(0);
            $t->decimal('total_ttc', 12, 2)->default(0);
            $t->decimal('montant_paye', 12, 2)->default(0);
            // Three statuses only, all derived by the system from what was
            // paid: validee (nothing), partielle (part), payee (in full).
            $t->enum('statut', ['validee', 'partielle', 'payee'])->default('validee');
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['date_facture', 'statut']);
            // The list sorts newest-first on date then id; without a matching
            // index MySQL reads the whole table and filesorts it every page.
            $t->index(['date_facture', 'id'], 'invoices_date_facture_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
