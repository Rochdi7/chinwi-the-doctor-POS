import { useCallback, useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';

/**
 * List filters kept in the URL (?q=...&page=2), so reload, back/forward and
 * a shared link all show the same list. Changing a filter goes back to page 1.
 */
export function useListParams<K extends string>(defaults: Record<K, string>) {
    const [params, setParams] = useSearchParams();

    const values = useMemo(() => {
        const out = { ...defaults, page: '1' } as Record<K | 'page', string>;
        for (const key of [...Object.keys(defaults), 'page'] as (K | 'page')[]) {
            const v = params.get(key);
            if (v !== null) out[key] = v;
        }
        return out;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [params]);

    const set = useCallback(
        (patch: Partial<Record<K | 'page', string>>) => {
            setParams(
                (prev) => {
                    const next = new URLSearchParams(prev);
                    for (const [k, v] of Object.entries(patch) as [string, string | undefined][]) {
                        if (v === undefined || v === '' || v === (defaults as Record<string, string>)[k]) next.delete(k);
                        else next.set(k, v);
                    }
                    if (!('page' in patch)) next.delete('page');
                    return next;
                },
                { replace: true },
            );
        },
        [setParams, defaults],
    );

    return [values, set] as const;
}
