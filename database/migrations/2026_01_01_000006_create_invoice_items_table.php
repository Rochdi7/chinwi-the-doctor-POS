<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $t->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $t->string('designation');
            $t->decimal('quantite', 12, 2)->default(1);
            $t->decimal('prix_unitaire', 12, 2)->default(0);
            $t->decimal('remise', 12, 2)->default(0);
            $t->decimal('tva', 5, 2)->default(20);
            $t->decimal('total_ht', 12, 2)->default(0);
            $t->decimal('total_ttc', 12, 2)->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
