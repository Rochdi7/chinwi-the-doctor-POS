import { useCallback, useSyncExternalStore } from 'react';

/** True while the media query matches; the component re-renders when that changes. */
export function useMediaQuery(query: string): boolean {
    const subscribe = useCallback(
        (notify: () => void) => {
            const mql = window.matchMedia(query);
            mql.addEventListener('change', notify);
            return () => mql.removeEventListener('change', notify);
        },
        [query],
    );
    return useSyncExternalStore(subscribe, () => window.matchMedia(query).matches, () => false);
}
