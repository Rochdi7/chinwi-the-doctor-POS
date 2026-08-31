<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->string('raison_sociale');
            $t->text('adresse')->nullable();
            $t->string('telephone', 40)->nullable();
            $t->string('email')->nullable();
            $t->string('ice', 40)->nullable();
            $t->string('rc', 40)->nullable();
            $t->string('numero_compte', 40)->nullable();
            $t->decimal('solde', 12, 2)->default(0);
            $t->boolean('actif')->default(true);
            $t->timestamps();
            $t->index('raison_sociale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
