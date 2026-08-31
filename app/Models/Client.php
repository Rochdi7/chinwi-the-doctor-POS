<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $guarded = [];

    protected $casts = ['solde' => 'decimal:2', 'actif' => 'boolean'];

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function recalcSolde(): void
    {
        $du = $this->invoices()->sum('total_ttc');
        $paye = $this->payments()->sum('montant');
        $this->updateQuietly(['solde' => $du - $paye]);
    }
}
