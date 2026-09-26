import { lazy, Suspense, type ReactNode } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { MutationCache, QueryCache, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApiError } from '@/lib/api';
import { SessionProvider, sessionKey, useSession } from '@/auth/session';
import { Toaster } from '@/components/ui/toast';
import { LiveSync } from '@/lib/sync';
import type { Session } from '@/types/api';
import LoginPage from '@/pages/LoginPage';

const PosPage = lazy(() => import('@/features/pos/PosPage'));
const AppLayout = lazy(() => import('@/layouts/AppLayout'));
const DashboardPage = lazy(() => import('@/pages/DashboardPage'));
const ArticlesPage = lazy(() => import('@/pages/ArticlesPage'));
const CategoriesPage = lazy(() => import('@/pages/CategoriesPage'));
const ClientsPage = lazy(() => import('@/pages/ClientsPage'));
const ClientDetailPage = lazy(() => import('@/pages/ClientsPage').then((m) => ({ default: m.ClientDetailPage })));
const VentesPage = lazy(() => import('@/pages/VentesPage'));
const VenteDetailPage = lazy(() => import('@/pages/VentesPage').then((m) => ({ default: m.VenteDetailPage })));
const VenteFormPage = lazy(() => import('@/pages/VentesPage').then((m) => ({ default: m.VenteFormPage })));
const ReglementsPage = lazy(() => import('@/pages/RecordsPages').then((m) => ({ default: m.ReglementsPage })));
const CaissePage = lazy(() => import('@/pages/RecordsPages').then((m) => ({ default: m.CaissePage })));
const JournalPage = lazy(() => import('@/pages/RecordsPages').then((m) => ({ default: m.JournalPage })));
const ParametresPage = lazy(() => import('@/pages/RecordsPages').then((m) => ({ default: m.ParametresPage })));

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

/** Logged out: to the login page, remembering where to come back to. */
function RequireAuth({ children }: { children: ReactNode }) {
    const { user } = useSession();
    const location = useLocation();
    return user ? children : <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />;
}

/** Logged in: straight to the till, whoever the user is and wherever they came from. */
function GuestOnly({ children }: { children: ReactNode }) {
    const { user } = useSession();
    return user ? <Navigate to="/pos" replace /> : children;
}

const loading = (
    <div className="grid h-full min-h-60 place-items-center">
        <span className="spinner size-8 text-brand" />
    </div>
);

export default function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <SessionProvider>
                {/* Same account on several devices: what one changes, the others show. */}
                <LiveSync />
                <BrowserRouter basename="/app">
                    <Suspense fallback={loading}>
                        <Routes>
                            <Route path="/login" element={<GuestOnly><LoginPage /></GuestOnly>} />
                            {/* Full-screen: the till. */}
                            <Route path="/pos" element={<RequireAuth><PosPage /></RequireAuth>} />
                            {/* Back office. */}
                            <Route element={<RequireAuth><AppLayout /></RequireAuth>}>
                                <Route index element={<Suspense fallback={loading}><DashboardPage /></Suspense>} />
                                <Route path="/ventes" element={<Suspense fallback={loading}><VentesPage /></Suspense>} />
                                <Route path="/ventes/nouvelle" element={<Suspense fallback={loading}><VenteFormPage /></Suspense>} />
                                <Route path="/ventes/:id" element={<Suspense fallback={loading}><VenteDetailPage /></Suspense>} />
                                <Route path="/ventes/:id/modifier" element={<Suspense fallback={loading}><VenteFormPage /></Suspense>} />
                                <Route path="/reglements" element={<Suspense fallback={loading}><ReglementsPage /></Suspense>} />
                                <Route path="/articles" element={<Suspense fallback={loading}><ArticlesPage /></Suspense>} />
                                <Route path="/categories" element={<Suspense fallback={loading}><CategoriesPage /></Suspense>} />
                                <Route path="/clients" element={<Suspense fallback={loading}><ClientsPage /></Suspense>} />
                                <Route path="/clients/:id" element={<Suspense fallback={loading}><ClientDetailPage /></Suspense>} />
                                <Route path="/caisse" element={<Suspense fallback={loading}><CaissePage /></Suspense>} />
                                <Route path="/journal" element={<Suspense fallback={loading}><JournalPage /></Suspense>} />
                                <Route path="/parametres" element={<Suspense fallback={loading}><ParametresPage /></Suspense>} />
                            </Route>
                            <Route path="*" element={<Navigate to="/" replace />} />
                        </Routes>
                    </Suspense>
                </BrowserRouter>
                <Toaster />
            </SessionProvider>
        </QueryClientProvider>
    );
}
