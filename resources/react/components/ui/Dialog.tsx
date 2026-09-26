import { useEffect, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { useT } from '@/auth/session';

interface DialogProps {
    open: boolean;
    onClose: () => void;
    title?: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    size?: 'sm' | 'md' | 'lg';
}

/**
 * The native <dialog>: focus trap, Escape and the backdrop come from the
 * browser, so the till behaves the same whatever has focus. The element
 * marked data-autofocus gets the focus (React's autoFocus does not set the
 * attribute <dialog> looks for, so the browser would pick the first button).
 */
export function Dialog({ open, onClose, title, description, children, footer, size = 'md' }: DialogProps) {
    const t = useT();
    const ref = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) {
            dialog.showModal();
            dialog.querySelector<HTMLElement>('[data-autofocus]')?.focus();
        }
        if (!open && dialog.open) dialog.close();
    }, [open]);

    return (
        <dialog
            ref={ref}
            onCancel={(e) => {
                e.preventDefault();
                onClose();
            }}
            onClick={(e) => {
                // A click on the backdrop lands on the <dialog> itself.
                if (e.target === ref.current) onClose();
            }}
            className={`m-auto w-[calc(100vw-2rem)] ${{ sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-4xl' }[size]} rounded-card border border-line bg-surface p-0 text-ink shadow-lift`}
        >
            {open && (
                <div className="flex max-h-[85dvh] flex-col">
                    {(title || description) && (
                        <div className="flex items-start gap-3 border-b border-line px-5 py-4">
                            <div className="min-w-0 flex-1">
                                {title && <h2 className="text-lg font-bold">{title}</h2>}
                                {description && <p className="mt-1 text-sm text-ink-2">{description}</p>}
                            </div>
                            <button className="btn btn-ghost -me-2 min-h-9 px-2" onClick={onClose} aria-label={t('spa.ui.fermer')}>
                                <X />
                            </button>
                        </div>
                    )}
                    {/* relative: an absolute child (a Toggle's sr-only checkbox) would
                        otherwise be placed against the <dialog> and make it scroll too. */}
                    <div className="relative min-h-0 overflow-y-auto px-5 py-4">{children}</div>
                    {footer && <div className="flex flex-wrap justify-end gap-2 border-t border-line bg-surface-2 px-5 py-3">{footer}</div>}
                </div>
            )}
        </dialog>
    );
}
