import { CheckCircle2, AlertTriangle, XCircle, Info, X } from 'lucide-react';
import { create } from 'zustand';

export type ToastTone = 'success' | 'error' | 'warning' | 'info';

export interface Toast {
    id: number;
    tone: ToastTone;
    title: string;
    body?: string;
}

interface ToastState {
    toasts: Toast[];
    push: (toast: Omit<Toast, 'id'>) => void;
    dismiss: (id: number) => void;
}

let next = 1;

/** Short, quiet notices. Errors stay a little longer than confirmations. */
export const useToasts = create<ToastState>((set, get) => ({
    toasts: [],
    push: (toast) => {
        const id = next++;
        // Keep at most 4 on screen: a burst of scans must not bury the till.
        set({ toasts: [...get().toasts.slice(-3), { ...toast, id }] });
        window.setTimeout(() => get().dismiss(id), toast.tone === 'error' ? 5000 : 2800);
    },
    dismiss: (id) => set({ toasts: get().toasts.filter((t) => t.id !== id) }),
}));

export const toast = {
    success: (title: string, body?: string) => useToasts.getState().push({ tone: 'success', title, body }),
    error: (title: string, body?: string) => useToasts.getState().push({ tone: 'error', title, body }),
    warning: (title: string, body?: string) => useToasts.getState().push({ tone: 'warning', title, body }),
    info: (title: string, body?: string) => useToasts.getState().push({ tone: 'info', title, body }),
};

const tones: Record<ToastTone, { icon: typeof Info; className: string }> = {
    success: { icon: CheckCircle2, className: 'text-ok' },
    error: { icon: XCircle, className: 'text-bad' },
    warning: { icon: AlertTriangle, className: 'text-warn' },
    info: { icon: Info, className: 'text-brand' },
};

export function Toaster() {
    const toasts = useToasts((s) => s.toasts);
    const dismiss = useToasts((s) => s.dismiss);

    return (
        <div aria-live="polite" className="pointer-events-none fixed bottom-4 end-4 z-50 flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2">
            {toasts.map((t) => {
                const { icon: Icon, className } = tones[t.tone];
                return (
                    <div key={t.id} role={t.tone === 'error' ? 'alert' : 'status'} className="panel pointer-events-auto flex animate-rise items-start gap-3 p-3 shadow-lift">
                        <Icon className={`mt-0.5 size-5 flex-none ${className}`} />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold break-words">{t.title}</p>
                            {t.body && <p className="mt-0.5 text-sm text-ink-2 break-words">{t.body}</p>}
                        </div>
                        <button className="rounded p-1 text-ink-3 hover:bg-surface-2 hover:text-ink" onClick={() => dismiss(t.id)} aria-label="×">
                            <X className="size-4" />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
