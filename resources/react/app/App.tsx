import { lazy, Suspense, type ReactNode } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { MutationCache, QueryCache, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiError } from '@/lib/api';
import { SessionProvider, sessionKey, useSession } from '@/auth/session';
import { Toaster } from '@/components/ui/toast';
import type { Session } from '@/types/api';
import LoginPage from '@/pages/LoginPage';

const PosPage = lazy(() => import('@/features/pos/PosPage'));

/**
 * A request answered 401/419 means the Laravel session is gone (expired,
 * logged out elsewhere): drop the user, and the router sends them to login.
 */
function onApiError(error: unknown) {
    if (error instanceof ApiError && (error.kind === 'auth' || error.kind === 'csrf')) {
        queryClient.setQueryData<Session>(sessionKey, (s) => (s ? { ...s, user: null } : s));
    }
}

const queryClient = new QueryClient({
    queryCache: new QueryCache({ onError: onApiError }),
    mutationCache: new MutationCache({ onError: onApiError }),
    defaultOptions: {
        queries: {
            refetchOnWindowFocus: false,
            // Retry only when the server could not be reached at all.
            retry: (count, error) => error instanceof ApiError && error.kind === 'network' && count < 2,
        },
        mutations: { retry: false },
    },
});

function RequireAuth({ children }: { children: ReactNode }) {
    const { user } = useSession();
    return user ? children : <Navigate to="/login" replace />;
}

function GuestOnly({ children }: { children: ReactNode }) {
    const { user } = useSession();
    return user ? <Navigate to="/pos" replace /> : children;
}

const loading = (
    <div className="grid h-full place-items-center">
        <span className="spinner size-8 text-brand" />
    </div>
);

export default function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <SessionProvider>
                <BrowserRouter basename="/app">
                    <Suspense fallback={loading}>
                        <Routes>
                            <Route path="/login" element={<GuestOnly><LoginPage /></GuestOnly>} />
                            <Route path="/pos" element={<RequireAuth><PosPage /></RequireAuth>} />
                            <Route path="*" element={<Navigate to="/pos" replace />} />
                        </Routes>
                    </Suspense>
                </BrowserRouter>
                <Toaster />
            </SessionProvider>
        </QueryClientProvider>
    );
}
