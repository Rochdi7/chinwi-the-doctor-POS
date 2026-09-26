import { useEffect, useRef, useState } from 'react';
import { Camera, Trash2 } from 'lucide-react';
import { useSession, useSessionActions, useT } from '@/auth/session';
import { Dialog } from '@/components/ui/Dialog';
import { TextField, fieldErrors } from '@/components/ui/form';
import { toast } from '@/components/ui/toast';
import { UserAvatar } from '@/components/UserAvatar';
import { ApiError, errorMessage } from '@/lib/api';

/** The logged-in user's own name, photo and password. */
export function ProfileDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const { user } = useSession();
    const { updateProfile, uploadAvatar, removeAvatar } = useSessionActions();
    const t = useT();
    const file = useRef<HTMLInputElement>(null);
    const [name, setName] = useState('');
    const [current, setCurrent] = useState('');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [photoBusy, setPhotoBusy] = useState(false);

    useEffect(() => {
        if (!open) return;
        setName(user?.name ?? '');
        setCurrent('');
        setPassword('');
        setConfirmation('');
        setErrors({});
    }, [open]); // eslint-disable-line react-hooks/exhaustive-deps

    const onPhoto = async (picked: File | undefined) => {
        if (!picked) return;
        setPhotoBusy(true);
        try {
            await uploadAvatar(picked);
            toast.success(t('spa.profil.photo_enregistree'));
        } catch (e) {
            toast.error(e instanceof ApiError && e.kind === 'validation' ? Object.values(fieldErrors(e.errors))[0] ?? errorMessage(e, t) : errorMessage(e, t));
        } finally {
            setPhotoBusy(false);
            if (file.current) file.current.value = '';
        }
    };

    const onRemovePhoto = async () => {
        setPhotoBusy(true);
        try {
            await removeAvatar();
            toast.success(t('spa.profil.photo_retiree'));
        } catch (e) {
            toast.error(errorMessage(e, t));
        } finally {
            setPhotoBusy(false);
        }
    };

    const submit = async () => {
        setErrors({});
        setSaving(true);
        try {
            await updateProfile(password ? { name, current_password: current, password, password_confirmation: confirmation } : { name });
            toast.success(t('spa.profil.enregistre'));
            onClose();
        } catch (e) {
            if (e instanceof ApiError && e.kind === 'validation') setErrors(fieldErrors(e.errors));
            else toast.error(errorMessage(e, t));
        } finally {
            setSaving(false);
        }
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            size="sm"
            title={t('spa.profil.titre')}
            description={t('spa.profil.sous_titre')}
            footer={
                <>
                    <button className="btn btn-secondary" onClick={onClose}>{t('spa.ui.annuler')}</button>
                    <button className="btn btn-primary" onClick={() => void submit()} disabled={saving}>
                        {saving && <span className="spinner size-4" />}
                        {t('spa.ui.enregistrer')}
                    </button>
                </>
            }
        >
            <form className="space-y-5" onSubmit={(e) => { e.preventDefault(); void submit(); }}>
                <div className="flex items-center gap-4">
                    <UserAvatar user={user} className="size-20 text-2xl" />
                    <div className="flex flex-wrap gap-2">
                        <input ref={file} type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(e) => void onPhoto(e.target.files?.[0])} />
                        <button type="button" className="btn btn-secondary" disabled={photoBusy} onClick={() => file.current?.click()}>
                            {photoBusy ? <span className="spinner size-4" /> : <Camera className="size-4" />}
                            {t('spa.profil.choisir_photo')}
                        </button>
                        {user?.avatar_url && (
                            <button type="button" className="btn btn-ghost text-bad" disabled={photoBusy} onClick={() => void onRemovePhoto()}>
                                <Trash2 className="size-4" />
                                {t('spa.profil.retirer_photo')}
                            </button>
                        )}
                    </div>
                </div>

                <TextField label={t('spa.profil.nom')} value={name} onChange={setName} error={errors.name} data-autofocus autoComplete="name" />
                <TextField label={t('spa.auth.email')} value={user?.email} onChange={() => {}} disabled />

                <fieldset className="space-y-3 rounded-card border border-line p-4">
                    <legend className="px-1 text-sm font-bold">{t('spa.profil.mot_de_passe')}</legend>
                    <p className="text-xs text-ink-3">{t('spa.profil.mot_de_passe_aide')}</p>
                    <TextField type="password" label={t('spa.profil.actuel')} value={current} onChange={setCurrent} error={errors.current_password} autoComplete="current-password" />
                    <TextField type="password" label={t('spa.profil.nouveau')} value={password} onChange={setPassword} error={errors.password} autoComplete="new-password" />
                    <TextField type="password" label={t('spa.profil.confirmation')} value={confirmation} onChange={setConfirmation} autoComplete="new-password" />
                </fieldset>
                <button type="submit" className="sr-only" tabIndex={-1} aria-hidden />
            </form>
        </Dialog>
    );
}
