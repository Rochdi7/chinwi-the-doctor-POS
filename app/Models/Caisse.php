<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Caisse extends Model
{
    protected $table = 'caisse';

    protected $guarded = [];

    protected $casts = ['solde' => 'decimal:2'];

    public static function instance(): self
    {
        return static::firstOrCreate(['id' => 1], ['solde' => 0]);
    }

    public static function solde(): float
    {
        return (float) static::instance()->solde;
    }

    /**
     * Single shared drawer: lock the row, move the money, record the movement.
     */
    public static function mouvement(string $type, float $montant, string $motif, ?int $paymentId = null): CaisseMouvement
    {
        return DB::transaction(function () use ($type, $montant, $motif, $paymentId) {
            static::instance();
            $caisse = static::whereKey(1)->lockForUpdate()->first();

            $avant = (float) $caisse->solde;
            $apres = $type === 'entree' ? $avant + $montant : $avant - $montant;

            $caisse->updateQuietly(['solde' => $apres]);

            $mvt = CaisseMouvement::create([
                'user_id' => auth()->id(),
                'payment_id' => $paymentId,
                'type' => $type,
                'montant' => $montant,
                'solde_avant' => $avant,
                'solde_apres' => $apres,
                'motif' => $motif,
                'occurred_at' => now()->format('Y-m-d H:i:s.v'),
            ]);

            ActivityLog::record(
                'caisse.'.$type,
                $mvt,
                $motif,
                $montant,
                ['solde_avant' => $avant, 'solde_apres' => $apres],
            );

            return $mvt;
        });
    }
}
