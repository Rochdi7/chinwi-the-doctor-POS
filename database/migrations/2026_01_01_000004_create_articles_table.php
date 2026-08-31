<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 60)->unique();
            // Scanned at the till. Nullable: not every article carries one,
            // though the app generates a code for each new article.
            $t->string('code_barre', 64)->nullable()->unique();
            $t->string('designation');
            $t->string('unite', 20)->default('Unite');
            $t->string('marque', 60)->nullable();
            $t->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->decimal('prix_achat', 12, 2)->default(0);
            $t->decimal('prix_vente', 12, 2)->default(0);
            $t->decimal('stock', 12, 2)->default(0);
            $t->decimal('tva', 5, 2)->default(20);
            $t->boolean('actif')->default(true);
            $t->timestamps();
            $t->index('designation');
            // The article picker filters on actif before searching the
            // designation; without this the filter is a full scan per keystroke.
            $t->index(['actif', 'designation'], 'articles_actif_designation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
