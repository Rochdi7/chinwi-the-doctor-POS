import { useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import {
    LayoutDashboard, ShoppingCart, FileText, Banknote, Package, Tags, Users, Wallet, ScrollText, Settings, LogOut, Menu, X,
} from 'lucide-react';
import { useSession, useSessionActions, useT } from '@/auth/session';
import { LanguageSwitch } from '@/components/LanguageSwitch';
import { AlertsBell } from '@/components/AlertsBell';
import { ProfileDialog } from '@/components/ProfileDialog';
import { UserAvatar } from '@/components/UserAvatar';

interface Item { to: string; icon: typeof LayoutDashboard; label: string; end?: boolean }

/** The same groups, in the same order, as the former panel menu. */
function useNav(): { group: string | null; items: Item[] }[] {
    const t = useT();

    return [
        { group: null, items: [{ to: '/', icon: LayoutDashboard, label: t('spa.ui.tableau_de_bord'), end: true }] },
        {
            group: t('nav.vente'),
            items: [
                { to: '/pos', icon: ShoppingCart, label: t('pos.label') },
                { to: '/ventes', icon: FileText, label: t('invoice.plural') },
                { to: '/reglements', icon: Banknote, label: t('payment.plural') },
            ],
        },
        {
            group: t('nav.donnees'),
            items: [
                { to: '/articles', icon: Package, label: t('article.plural') },
                { to: '/categories', icon: Tags, label: t('categorie.plural') },
                { to: '/clients', icon: Users, label: t('client.plural') },
            ],
        },
        { group: t('nav.caisse'), items: [{ to: '/caisse', icon: Wallet, label: t('caisse.mouvements') }] },
        {
            group: t('nav.securite'),
            items: [
                { to: '/journal', icon: ScrollText, label: t('log.plural') },
                { to: '/parametres', icon: Settings, label: t('setting.plural') },
            ],
        },
    ];
}

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
    const { societe } = useSession();
    const t = useT();
    const nav = useNav();

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-center gap-2.5 border-b border-white/10 px-4 py-3.5">
                <img src="/assets/chinwi-the-doctor.jpeg" alt={societe ?? ""} className="h-9 w-auto rounded-md bg-white object-contain" />
            </div>

            <nav className="flex-1 space-y-4 overflow-y-auto px-2.5 py-4">
                {nav.map((section, i) => (
                    <div key={i}>
                        {section.group && <p className="mb-1 px-2.5 text-[0.68rem] font-bold tracking-wider text-white/45 uppercase rtl:text-xs rtl:tracking-normal">{section.group}</p>}
                        <ul className="space-y-0.5">
                            {section.items.map(({ to, icon: Icon, label, end }) => (
                                <li key={to}>
                                    <NavLink
                                        to={to}
                                        end={end}
                                        onClick={onNavigate}
                                        className={({ isActive }) =>
                                            `flex min-h-10 items-center gap-2.5 rounded-ctl px-2.5 text-sm font-semibold transition-colors ${
                                                isActive ? 'bg-white text-navy shadow-card' : 'text-white/75 hover:bg-white/10 hover:text-white'
                                            }`
                                        }
                                    >
                                        <Icon className="size-[1.15rem] flex-none" />
                                        <span className="truncate">{label}</span>
                                    </NavLink>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </nav>

            <div className="border-t border-white/10 p-3">
                <NavLink to="/pos" onClick={onNavigate} className="btn btn-success w-full">
                    <ShoppingCart />
                    {t('spa.ui.caisse')}
                </NavLink>
            </div>
        </div>
    );
}

/** Back-office frame: sidebar, top bar, the screen. The till has its own. */
export default function AppLayout() {
    const { user } = useSession();
    const { logout } = useSessionActions();
    const t = useT();
    const [open, setOpen] = useState(false);
    const [profile, setProfile] = useState(false);
    const location = useLocation();

    useEffect(() => setOpen(false), [location.pathname]);

    return (
        <div className="flex h-dvh">
            <aside className="hidden w-60 flex-none bg-navy lg:block">
                <Sidebar />
            </aside>

            {open && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button className="absolute inset-0 bg-ink/50" onClick={() => setOpen(false)} aria-label={t('spa.ui.fermer')} />
                    <aside className="absolute inset-y-0 start-0 w-64 animate-rise bg-navy shadow-lift">
                        <Sidebar onNavigate={() => setOpen(false)} />
                    </aside>
                </div>
            )}

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-14 flex-none items-center gap-3 border-b border-line bg-surface px-3 sm:px-5">
                    <button className="btn btn-ghost min-h-10 px-2.5 lg:hidden" onClick={() => setOpen(true)} aria-label={t('spa.ui.menu')}>
                        {open ? <X /> : <Menu />}
                    </button>
                    <div className="ms-auto flex items-center gap-2">
                        <AlertsBell />
                        <LanguageSwitch compact />
                        <div className="flex h-10 items-center gap-1 rounded-full border border-line">
                            <button className="flex h-10 items-center gap-2 rounded-s-full ps-1.5 pe-1 hover:bg-surface-2" onClick={() => setProfile(true)} title={t('spa.profil.titre')} aria-label={t('spa.profil.titre')}>
                                <UserAvatar user={user} />
                                <span className="hidden max-w-36 truncate text-sm font-semibold sm:inline">{user?.name}</span>
                            </button>
                            <button className="btn btn-ghost min-h-9 rounded-full px-2.5" onClick={() => void logout()} title={t('spa.auth.deconnexion')} aria-label={t('spa.auth.deconnexion')}>
                                <LogOut className="rtl:-scale-x-100" />
                            </button>
                        </div>
                    </div>
                </header>
                <ProfileDialog open={profile} onClose={() => setProfile(false)} />

                <main className="min-h-0 flex-1 overflow-y-auto">
                    <div className="mx-auto max-w-[90rem] p-3 sm:p-5">
                        <Outlet />
                    </div>
                </main>
            </div>
        </div>
    );
}
