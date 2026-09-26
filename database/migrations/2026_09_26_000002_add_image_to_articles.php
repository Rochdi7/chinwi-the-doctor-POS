<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product photo, optional: the path of a PNG on the public disk
 * (storage/app/public/articles/{id}.png), null when there is none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', fn (Blueprint $t) => $t->string('image')->nullable()->after('marque'));
    }

    public function down(): void
    {
        Schema::table('articles', fn (Blueprint $t) => $t->dropColumn('image'));
    }
};
