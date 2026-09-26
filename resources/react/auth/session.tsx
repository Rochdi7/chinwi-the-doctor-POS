import { createContext, useCallback, useContext, useEffect, useMemo, type ReactNode } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import type { Messages, Session } from '@/types/api';

const SessionContext = createContext<Session | null>(null);

export const sessionKey = ['session'] as const;

/**
 * Loads who is logged in, the language and its words, once. Everything
 * below can assume they exist.
 */
export function SessionProvider({ children }: { children: ReactNode }) {
    const query = useQuery({
        queryKey: sessionKey,
        queryFn: () => api<Session>('/session'),
        staleTime: Infinity,
    });

    const session = query.data;

    // The whole document flips for Arabic and Darija; no separate layout.
    useEffect(() => {
        if (!session) return;
        // Browsers and screen readers do not know 'ary': tell them it is Moroccan Arabic.
        document.documentElement.lang = session.locale === 'ary' ? 'ar-MA' : session.locale;
        document.documentElement.dir = session.dir;
        const pos = (session.messages.pos as Record<string, string> | undefined)?.label;
        if (pos) document.title = session.societe ? `${session.societe} — ${pos}` : pos;
    }, [session]);

    if (query.isPending) {
        return <div className="grid h-full place-items-center"><span className="spinner size-8 text-brand" /></div>;
    }

    if (!session) {
        // No words yet to say it in the user's language: say it in both.
        return (
            <div className="grid h-full place-items-center p-6 text-center">
                <div className="space-y-4">
                    <p className="text-lg font-semibold">Connexion au serveur impossible.</p>
                    <p className="text-lg font-semibold" dir="rtl">تعذر الاتصال بالخادم.</p>
                    <button className="btn btn-secondary" onClick={() => void query.refetch()}>↻</button>
                </div>
            </div>
        );
    }

    return <SessionContext.Provider value={session}>{children}</SessionContext.Provider>;
}

export function useSession(): Session {
    const session = useContext(SessionContext);
    if (!session) throw new Error('useSession() outside <SessionProvider>');
    return session;
}

/** Login, logout and language switch; each answers with the new session. */
export function useSessionActions() {
    const queryClient = useQueryClient();

    const apply = useCallback(
        (next: Session) => {
            queryClient.setQueryData(sessionKey, next);
            // Server-side words (units, scanner labels) change with the language.
            void queryClient.invalidateQueries({ predicate: (q) => q.queryKey[0] !== sessionKey[0] });
        },
        [queryClient],
    );

    return {
        login: async (email: string, password: string, remember: boolean) =>
            apply(await api<Session>('/login', { method: 'POST', body: { email, password, remember } })),
        logout: async () => {
            apply(await api<Session>('/logout', { method: 'POST' }));
            queryClient.removeQueries({ predicate: (q) => q.queryKey[0] !== sessionKey[0] });
        },
        setLocale: async (locale: string) => apply(await api<Session>('/locale', { method: 'POST', body: { locale } })),
    };
}

export type Translate = (key: string, params?: Record<string, string | number>) => string;

/**
 * Laravel's __() on the client: same keys, same ":param" placeholders, same
 * words, because the messages are lang/{locale}/app.php itself.
 */
export function useT(): Translate {
    const { messages } = useSession();

    return useMemo(() => {
        const lookup = (key: string): string | null => {
            let node: string | Messages | undefined = messages;
            for (const part of key.split('.')) {
                if (typeof node !== 'object') return null;
                node = node[part];
            }
            return typeof node === 'string' ? node : null;
        };

        return (key, params) => {
            let text = lookup(key) ?? key;
            if (params) {
                // Longest names first, so ":article" is not eaten by ":a".
                for (const name of Object.keys(params).sort((a, b) => b.length - a.length)) {
                    text = text.split(':' + name).join(String(params[name]));
                }
            }
            return text;
        };
    }, [messages]);
}
