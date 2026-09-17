<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    protected $guarded = [];

    protected $casts = [
        'prix_achat' => 'decimal:2',
        'prix_vente' => 'decimal:2',
        'stock' => 'decimal:2',
        'tva' => 'decimal:2',
        'actif' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * What the scanner sends: usually the barcode, but a typed reference
     * should work too so the till still functions without a label.
     */
    public static function findByScan(string $code): ?self
    {
        // Undo an AZERTY layout turning the digits into punctuation.
        $code = \App\Support\Barcode::normalizeScan($code);

        if ($code === '') {
            return null;
        }

        // A barcode match wins over a reference match: nothing stops one
        // article's reference from colliding with another's barcode, and
        // the scanner is always sending a barcode.
        return static::query()
            ->where('actif', true)
            ->where(fn ($q) => $q->where('code_barre', $code)->orWhere('reference', $code))
            ->orderByRaw('code_barre = ? desc', [$code])
            ->first();
    }
}
