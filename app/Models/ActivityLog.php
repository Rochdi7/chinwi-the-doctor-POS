<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'properties' => 'array',
        'occurred_at' => 'datetime:Y-m-d H:i:s.v',
        'montant' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * CCTV-matchable audit line, millisecond precision.
     */
    public static function record(
        string $event,
        ?Model $subject = null,
        ?string $description = null,
        ?float $montant = null,
        array $props = [],
    ): self {
        return static::create([
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'event' => $event,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'montant' => $montant,
            'properties' => $props ?: null,
            'ip' => request()->ip(),
            'occurred_at' => now()->format('Y-m-d H:i:s.v'),
        ]);
    }
}
