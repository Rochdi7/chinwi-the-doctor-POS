import { useState } from 'react';
import { Combobox } from '@/components/ui/Combobox';
import { Flag } from '@/components/Flag';
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
        <Combobox
            size="sm"
            className={compact ? 'w-auto' : ''}
            value={locale}
            disabled={busy}
            aria-label={t('spa.pos.langue')}
            options={locales.map((l) => ({ value: l.code, label: l.label, icon: <Flag locale={l.code} /> }))}
            onChange={async (code) => {
                setBusy(true);
                try {
                    await setLocale(code);
                } catch (error) {
                    toast.error(errorMessage(error, t));
                } finally {
                    setBusy(false);
                }
            }}
        />
    );
}
