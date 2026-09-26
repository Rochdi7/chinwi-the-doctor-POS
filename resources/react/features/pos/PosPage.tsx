import { useSession, useT } from '@/auth/session';

/** Placeholder until the till lands (next commits). */
export default function PosPage() {
    const { user } = useSession();
    const t = useT();

    return <div className="grid h-full place-items-center text-xl font-bold">{t('pos.label')} — {user?.name}</div>;
}
