<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    /** Photos live on the public disk; a replaced or orphaned one is removed. */
    public const IMAGE_DISK = 'public';

    protected static function booted(): void
    {
        static::updated(function (Article $article) {
            if ($article->wasChanged('image') && $old = $article->getOriginal('image')) {
                Storage::disk(self::IMAGE_DISK)->delete($old);
            }
        });

        static::deleted(function (Article $article) {
            if ($article->image) {
                Storage::disk(self::IMAGE_DISK)->delete($article->image);
            }
        });
    }

    public function imageUrl(): ?string
    {
        // asset() follows the host the till is opened on; the disk URL
        // would be pinned to APP_URL (http://localhost).
        return $this->image ? asset('storage/'.$this->image) : null;
    }

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
