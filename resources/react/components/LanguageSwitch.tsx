import { useState } from 'react';
import { Languages } from 'lucide-react';
import { useSession, useSessionActions, useT } from '@/auth/session';
import { errorMessage } from '@/lib/api';
import { toast } from '@/components/ui/toast';

/** Français / العربية / الدارجة: the same three choices as the panel menu. */
export function LanguageSwitch({ compact = false }: { compact?: boolean }) {
    const { locale, locales } = useSession();
    const { setLocale } = useSessionActions();
    const t = useT();
    const [busy, setBusy] = useState(false);

    return (
        <label className="relative inline-flex items-center" title={t('spa.pos.langue')}>
            <Languages className="pointer-events-none absolute start-2.5 size-4 text-ink-3" />
            <select
                className={`field min-h-10 ps-8 text-sm font-semibold ${compact ? 'w-auto' : ''}`}
                value={locale}
                disabled={busy}
                aria-label={t('spa.pos.langue')}
                onChange={async (e) => {
                    setBusy(true);
                    try {
                        await setLocale(e.target.value);
                    } catch (error) {
                        toast.error(errorMessage(error, t));
                    } finally {
                        setBusy(false);
                    }
                }}
            >
                {locales.map((l) => (
                    <option key={l.code} value={l.code}>{l.label}</option>
                ))}
            </select>
        </label>
    );
}
