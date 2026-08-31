<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaisseMouvement extends Model
{
    protected $table = 'caisse_mouvements';

    protected $guarded = [];

    protected $casts = [
        'montant' => 'decimal:2',
        'solde_avant' => 'decimal:2',
        'solde_apres' => 'decimal:2',
        'occurred_at' => 'datetime:Y-m-d H:i:s.v',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
