<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'date_facture' => 'date',
        'total_ht' => 'decimal:2',
        'total_tva' => 'decimal:2',
        'remise' => 'decimal:2',
        'total_ttc' => 'decimal:2',
        'montant_paye' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public static function nextNumero(): string
    {
        $year = date('Y');
        $last = static::where('numero', 'like', $year.'-%')->orderByDesc('id')->value('numero');
        $n = $last ? ((int) substr($last, 5)) + 1 : 1;

        return $year.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** The only statuses that exist, in lifecycle order. */
    public const STATUTS = ['validee', 'partielle', 'payee'];

    /** @return array<string, string> value => translated label */
    public static function statutOptions(): array
    {
        return collect(static::STATUTS)
            ->mapWithKeys(fn (string $s) => [$s => __('app.statut.'.$s)])
            ->all();
    }

    public static function statutColor(string $statut): string
    {
        return match ($statut) {
            'payee' => 'success',
            'partielle' => 'warning',
            default => 'info',
        };
    }

    /**
     * The status is never chosen by hand: it follows the money.
     */
    public static function deriveStatut(float $ttc, float $paye): string
    {
        if ($paye <= 0) {
            return 'validee';
        }

        // A centime of tolerance keeps rounding from leaving an invoice
        // "partielle" when the client has actually settled it.
        return $paye + 0.01 >= $ttc ? 'payee' : 'partielle';
    }

    /** Buyer's name, or the walk-in label when the sale had no client. */
    public function clientNom(): string
    {
        return $this->client?->raison_sociale ?? __('app.vente.client_passage');
    }

    public function reste(): float
    {
        return round((float) $this->total_ttc - (float) $this->montant_paye, 2);
    }

    public function recalcTotals(): void
    {
        $items = $this->items()->get();
        $ht = (float) $items->sum('total_ht');
        $ttc = (float) $items->sum('total_ttc');
        $paye = (float) $this->payments()->sum('montant');

        $this->updateQuietly([
            'total_ht' => $ht,
            'total_tva' => $ttc - $ht,
            'total_ttc' => $ttc,
            'montant_paye' => $paye,
            'statut' => static::deriveStatut($ttc, $paye),
        ]);

        $this->client?->recalcSolde();
    }
}
