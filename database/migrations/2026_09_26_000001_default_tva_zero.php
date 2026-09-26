<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The shop sells without VAT: a new article or sale line starts at 0 %.
 * Existing articles keep the rate they were saved with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', fn (Blueprint $t) => $t->decimal('tva', 5, 2)->default(0)->change());
        Schema::table('invoice_items', fn (Blueprint $t) => $t->decimal('tva', 5, 2)->default(0)->change());

        DB::table('settings')->updateOrInsert(['key' => 'tva_defaut'], ['value' => '0']);
    }

    public function down(): void
    {
        Schema::table('articles', fn (Blueprint $t) => $t->decimal('tva', 5, 2)->default(20)->change());
        Schema::table('invoice_items', fn (Blueprint $t) => $t->decimal('tva', 5, 2)->default(20)->change());
    }
};
