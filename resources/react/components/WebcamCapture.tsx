import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Camera, Check, RotateCcw } from 'lucide-react';
import { useT } from '@/auth/session';
import { Dialog } from '@/components/ui/Dialog';

interface Props {
    open: boolean;
    onClose: () => void;
    /** The shot, as a file: it goes through the same path as a picked one. */
    onCapture: (file: File) => void;
}

type Failure = 'refusee' | 'absente' | 'occupee' | 'https';

function failureOf(error: unknown): Failure {
    const name = error instanceof DOMException ? error.name : '';
    if (name === 'NotAllowedError' || name === 'SecurityError') return 'refusee';
    if (name === 'NotFoundError' || name === 'OverconstrainedError') return 'absente';
    return 'occupee';
}

/**
 * Take the product photo with the computer's webcam, no file to find: live
 * preview, one shot, keep it or take it again. The stream is stopped as
 * soon as the dialog closes so the camera light goes off.
 */
export function WebcamCapture({ open, onClose, onCapture }: Props) {
    const t = useT();
    const video = useRef<HTMLVideoElement>(null);
    const stream = useRef<MediaStream | null>(null);
    const [devices, setDevices] = useState<MediaDeviceInfo[]>([]);
    // `chosen` is what the user picked (a change restarts the camera);
    // `active` is what is running, shown in the picker.
    const [chosen, setChosen] = useState<string>('');
    const [active, setActive] = useState<string>('');
    const [state, setState] = useState<'starting' | 'live' | 'failed'>('starting');
    const [failure, setFailure] = useState<Failure>('occupee');
    const [shot, setShot] = useState<{ blob: Blob; url: string } | null>(null);
    const [attempt, setAttempt] = useState(0);

    const stop = useCallback(() => {
        stream.current?.getTracks().forEach((track) => track.stop());
        stream.current = null;
    }, []);

    // Start (or switch) the camera while the dialog is open and no shot is on screen.
    useEffect(() => {
        if (!open || shot) return;
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            setFailure('https');
            setState('failed');
            return;
        }

        let cancelled = false;
        setState('starting');
        const constraints: MediaStreamConstraints = {
            audio: false,
            video: chosen ? { deviceId: { exact: chosen }, width: { ideal: 1920 }, height: { ideal: 1080 } } : { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } },
        };

        navigator.mediaDevices
            .getUserMedia(constraints)
            .then(async (media) => {
                if (cancelled) {
                    media.getTracks().forEach((track) => track.stop());
                    return;
                }
                stop();
                stream.current = media;
                if (video.current) {
                    video.current.srcObject = media;
                    await video.current.play().catch(() => {});
                }
                setState('live');
                // Labels are only filled in once the camera is allowed.
                const all = await navigator.mediaDevices.enumerateDevices();
                if (!cancelled) setDevices(all.filter((d) => d.kind === 'videoinput'));
                if (!cancelled) setActive(media.getVideoTracks()[0]?.getSettings().deviceId ?? '');
            })
            .catch((error: unknown) => {
                if (cancelled) return;
                setFailure(failureOf(error));
                setState('failed');
            });

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, shot, chosen, attempt]);

    // Closing the dialog releases the camera and forgets the shot.
    useEffect(() => {
        if (open) return;
        stop();
        setShot((s) => {
            if (s) URL.revokeObjectURL(s.url);
            return null;
        });
    }, [open, stop]);

    useEffect(() => stop, [stop]);

    const capture = () => {
        const v = video.current;
        if (!v || v.videoWidth === 0) return;
        const canvas = document.createElement('canvas');
        canvas.width = v.videoWidth;
        canvas.height = v.videoHeight;
        canvas.getContext('2d')?.drawImage(v, 0, 0);
        canvas.toBlob(
            (blob) => {
                if (!blob) return;
                stop();
                setShot({ blob, url: URL.createObjectURL(blob) });
            },
            'image/jpeg',
            0.92,
        );
    };

    const retake = () => {
        if (shot) URL.revokeObjectURL(shot.url);
        setShot(null);
    };

    const use = () => {
        if (!shot) return;
        onCapture(new File([shot.blob], 'camera.jpg', { type: 'image/jpeg' }));
        onClose();
    };

    // Portalled: the photo field sits inside the article <form>, and a
    // dialog nested there would submit it.
    return createPortal(
        <Dialog
            open={open}
            onClose={onClose}
            size="lg"
            title={t('spa.ui.camera_titre')}
            footer={
                shot ? (
                    <>
                        <button type="button" className="btn btn-secondary" onClick={retake}>
                            <RotateCcw />
                            {t('spa.ui.camera_reprendre')}
                        </button>
                        <button type="button" className="btn btn-primary" onClick={use} data-autofocus>
                            <Check />
                            {t('spa.ui.camera_utiliser')}
                        </button>
                    </>
                ) : (
                    <>
                        <button type="button" className="btn btn-secondary" onClick={onClose}>{t('spa.ui.annuler')}</button>
                        {state === 'failed' ? (
                            failure !== 'https' && (
                                <button type="button" className="btn btn-primary" onClick={() => setAttempt((n) => n + 1)}>
                                    <RotateCcw />
                                    {t('spa.ui.camera_reessayer')}
                                </button>
                            )
                        ) : (
                            <button type="button" className="btn btn-primary" onClick={capture} disabled={state !== 'live'} data-autofocus>
                                <Camera />
                                {t('spa.ui.camera_capturer')}
                            </button>
                        )}
                    </>
                )
            }
        >
            <div className="space-y-3">
                {devices.length > 1 && !shot && (
                    <label className="flex items-center gap-2 text-sm font-semibold">
                        <span className="flex-none">{t('spa.ui.camera_choix')}</span>
                        <select className="field min-h-10" value={active} onChange={(e) => setChosen(e.target.value)}>
                            {devices.map((d, i) => (
                                <option key={d.deviceId} value={d.deviceId}>{d.label || `${t('spa.ui.camera_choix')} ${i + 1}`}</option>
                            ))}
                        </select>
                    </label>
                )}

                <div className="relative grid aspect-video w-full place-items-center overflow-hidden rounded-ctl bg-ink">
                    {shot ? (
                        <img src={shot.url} alt="" className="size-full object-contain" />
                    ) : (
                        <video ref={video} className={`size-full object-contain ${state === 'live' ? '' : 'invisible'}`} playsInline muted />
                    )}
                    {!shot && state === 'starting' && (
                        <span className="absolute flex items-center gap-2 text-sm font-semibold text-white/80">
                            <span className="spinner size-5" />
                            {t('spa.ui.camera_demarrage')}
                        </span>
                    )}
                    {!shot && state === 'failed' && (
                        <p className="absolute max-w-md px-6 text-center text-sm font-semibold text-white">{t(`spa.ui.camera_${failure}`)}</p>
                    )}
                </div>
            </div>
        </Dialog>,
        document.body,
    );
}
