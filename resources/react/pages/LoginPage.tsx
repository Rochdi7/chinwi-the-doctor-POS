import { useState, type FormEvent } from 'react';
import { LogIn } from 'lucide-react';
import { useSession, useSessionActions, useT } from '@/auth/session';
import { ApiError, errorMessage } from '@/lib/api';
import { LanguageSwitch } from '@/components/LanguageSwitch';
import { Checkbox, TextField } from '@/components/ui/form';

/** Same users and passwords as the Filament panel login. */
export default function LoginPage() {
    const { societe } = useSession();
    const { login } = useSessionActions();
    const t = useT();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(true);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(e: FormEvent) {
        e.preventDefault();
        setBusy(true);
        setError(null);

        try {
            await login(email, password, remember);
        } catch (err) {
            setError(err instanceof ApiError && err.kind === 'validation' ? err.firstError() ?? t('spa.erreur.validation') : errorMessage(err, t));
            setBusy(false);
        }
    }

    return (
        <div className="flex min-h-full items-center justify-center p-4">
            <div className="w-full max-w-sm">
                <div className="mb-6 flex flex-col items-center gap-3 text-center">
                    <img src="/assets/chinwi-the-doctor.jpeg" alt="" className="h-16 w-auto rounded-lg border border-line bg-white object-contain p-1" />
                    <div>
                        <p className="text-sm font-semibold text-ink-2">{societe}</p>
                        <h1 className="text-2xl font-bold">{t('pos.label')}</h1>
                    </div>
                </div>

                <form onSubmit={submit} className="panel space-y-4 p-6" noValidate>
                    <div>
                        <h2 className="text-lg font-bold">{t('spa.auth.titre')}</h2>
                        <p className="text-sm text-ink-2">{t('spa.auth.sous_titre')}</p>
                    </div>

                    {error && (
                        <p role="alert" className="rounded-ctl border border-bad/30 bg-bad-soft px-3 py-2 text-sm font-medium text-bad-ink">
                            {error}
                        </p>
                    )}

                    <TextField label={t('spa.auth.email')} type="email" autoComplete="username" dir="ltr" required autoFocus value={email} onChange={setEmail} />
                    <TextField label={t('spa.auth.password')} type="password" autoComplete="current-password" dir="ltr" required value={password} onChange={setPassword} />
                    <Checkbox label={t('spa.auth.se_souvenir')} checked={remember} onChange={setRemember} />

                    <button type="submit" className="btn btn-primary w-full text-base" disabled={busy || !email || !password}>
                        {busy ? <span className="spinner size-5" /> : <LogIn className="rtl:-scale-x-100" />}
                        {t('spa.auth.connexion')}
                    </button>
                </form>

                <div className="mt-4 flex justify-center">
                    <LanguageSwitch compact />
                </div>
            </div>
        </div>
    );
}
