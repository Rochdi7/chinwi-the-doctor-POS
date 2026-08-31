<?php

namespace App\Support;

use App\Models\ActivityLog;

/**
 * Turns a raw activity_logs `properties` payload into readable avant/après
 * rows, so the journal answers "c'était quoi avant, c'est quoi maintenant ?"
 * without anyone having to read JSON.
 */
class AuditDiff
{
    /** Champs techniques qui n'apprennent rien au commerçant. */
    private const HIDDEN = [
        'id', 'created_at', 'updated_at', 'deleted_at',
        'password', 'remember_token', 'email_verified_at',
    ];

    /** Champs affichés en monnaie. */
    private const MONEY = [
        'solde', 'solde_avant', 'solde_apres', 'montant', 'prix_achat', 'prix_vente',
        'prix_unitaire', 'total_ht', 'total_tva', 'total_ttc', 'montant_paye',
        'remise', 'reste', 'sous_total',
    ];

    /**
     * @return array<int, array{champ: string, avant: string, apres: string, delta: ?string}>
     */
    public static function rows(ActivityLog $log): array
    {
        $props = $log->properties ?? [];

        // Mouvement de caisse : le solde avant/après est déjà stocké tel quel.
        if (array_key_exists('solde_avant', $props) || array_key_exists('solde_apres', $props)) {
            $avant = (float) ($props['solde_avant'] ?? 0);
            $apres = (float) ($props['solde_apres'] ?? 0);

            return [self::row('solde', $avant, $apres, 'Caisse')];
        }

        // Modification : on a l'ancien et le nouveau.
        if (isset($props['changes'])) {
            $old = $props['old'] ?? [];
            $rows = [];

            foreach ($props['changes'] as $field => $new) {
                if (self::hidden($field)) {
                    continue;
                }

                $rows[] = self::row($field, $old[$field] ?? null, $new, $log->subject_type);
            }

            return $rows;
        }

        // Création : rien avant, tout après. Suppression : tout avant, rien après.
        if (isset($props['attributes'])) {
            $rows = [];
            $isDelete = $log->event === 'deleted';

            foreach ($props['attributes'] as $field => $value) {
                if (self::hidden($field) || $value === null || $value === '') {
                    continue;
                }

                $rows[] = $isDelete
                    ? self::row($field, $value, null, $log->subject_type)
                    : self::row($field, null, $value, $log->subject_type);
            }

            return $rows;
        }

        // Payload libre (paramètres, impressions...) : on l'affiche à plat.
        $rows = [];

        foreach ($props as $field => $value) {
            if (self::hidden($field) || is_array($value)) {
                continue;
            }

            $rows[] = self::row($field, null, $value, $log->subject_type);
        }

        return $rows;
    }

    /**
     * Résumé d'une ligne pour la colonne du tableau, ex :
     * « Prix de vente : 120,00 DH → 99,00 DH (−21,00 DH) ».
     */
    public static function summary(ActivityLog $log, int $limit = 3): string
    {
        $rows = self::rows($log);

        if ($rows === []) {
            return '-';
        }

        $parts = [];

        foreach (array_slice($rows, 0, $limit) as $row) {
            $line = $row['champ'].' : '.$row['avant'].' → '.$row['apres'];

            if ($row['delta'] !== null) {
                $line .= ' ('.$row['delta'].')';
            }

            $parts[] = $line;
        }

        if (count($rows) > $limit) {
            $parts[] = '+'.(count($rows) - $limit).' autre(s)';
        }

        return implode("\n", $parts);
    }

    /**
     * Le log touche-t-il une remise ? Sert au badge « Réduction » du tableau.
     */
    public static function remise(ActivityLog $log): ?string
    {
        foreach (self::rows($log) as $row) {
            if (str_contains(mb_strtolower($row['champ']), 'remise')) {
                return $row['apres'];
            }
        }

        return null;
    }

    /**
     * @return array{champ: string, avant: string, apres: string, delta: ?string}
     */
    private static function row(string $field, mixed $avant, mixed $apres, ?string $subject = null): array
    {
        $delta = null;

        if (self::isMoney($field) && is_numeric($avant) && is_numeric($apres)) {
            $diff = (float) $apres - (float) $avant;

            if (abs($diff) >= 0.005) {
                $delta = ($diff > 0 ? '+' : '−').Money::format(abs($diff));
            }
        }

        return [
            'champ' => self::label($field, $subject),
            'avant' => self::value($field, $avant),
            'apres' => self::value($field, $apres),
            'delta' => $delta,
        ];
    }

    private static function hidden(string $field): bool
    {
        return in_array($field, self::HIDDEN, true) || str_ends_with($field, '_token');
    }

    private static function isMoney(string $field): bool
    {
        return in_array($field, self::MONEY, true);
    }

    /**
     * Traduit un nom de colonne en libellé métier.
     *
     * L'ordre des groupes compte : `remise` et `montant` existent dans
     * plusieurs sections, on interroge donc d'abord le groupe qui correspond
     * au type d'objet loggé, puis on retombe sur les autres.
     */
    private static function label(string $field, ?string $subject = null): string
    {
        $groups = ['article', 'client', 'invoice', 'item', 'payment', 'caisse'];

        $preferred = match ($subject) {
            'Article' => 'article',
            'Client' => 'client',
            'Invoice' => 'invoice',
            'InvoiceItem' => 'item',
            'Payment' => 'payment',
            'Caisse', 'CaisseMouvement' => 'caisse',
            default => null,
        };

        if ($preferred !== null) {
            array_unshift($groups, $preferred);
        }

        foreach ($groups as $group) {
            $key = 'app.'.$group.'.'.$field;
            $label = trans($key);

            if (is_string($label) && $label !== $key) {
                return $label;
            }
        }

        return ucfirst(str_replace('_', ' ', $field));
    }

    private static function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        if (self::isMoney($field) && is_numeric($value)) {
            return Money::format((float) $value);
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}
