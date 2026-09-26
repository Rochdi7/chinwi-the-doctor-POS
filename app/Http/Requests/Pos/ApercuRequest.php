<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A cart as the till holds it: article ids and quantities only. Prices, VAT
 * and totals are never accepted from the client.
 */
class ApercuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.article_id' => ['required', 'integer'],
            // Decimal quantities are legitimate (loose weight), as at the till.
            'items.*.quantite' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'montant_recu' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ];
    }
}
