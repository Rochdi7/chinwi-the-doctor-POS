<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantite' => 'decimal:2',
        'prix_unitaire' => 'decimal:2',
        'remise' => 'decimal:2',
        'tva' => 'decimal:2',
        'total_ht' => 'decimal:2',
        'total_ttc' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function computeTotals(): void
    {
        $ht = max(((float) $this->quantite * (float) $this->prix_unitaire) - (float) $this->remise, 0);
        $this->total_ht = round($ht, 2);
        $this->total_ttc = round($ht * (1 + (float) $this->tva / 100), 2);
    }
}
