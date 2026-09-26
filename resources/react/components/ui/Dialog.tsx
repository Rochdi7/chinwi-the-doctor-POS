import { useEffect, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';

interface DialogProps {
    open: boolean;
    onClose: () => void;
    title?: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    size?: 'sm' | 'md';
}

/**
 * The native <dialog>: focus trap, Escape and the backdrop come from the
 * browser, so the till behaves the same whatever has focus.
 */
export function Dialog({ open, onClose, title, description, children, footer, size = 'md' }: DialogProps) {
    const ref = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
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
            className={`m-auto w-[calc(100vw-2rem)] ${size === 'sm' ? 'max-w-md' : 'max-w-lg'} rounded-card border border-line bg-surface p-0 text-ink shadow-lift`}
        >
            {open && (
                <div className="flex max-h-[85dvh] flex-col">
                    {(title || description) && (
                        <div className="flex items-start gap-3 border-b border-line px-5 py-4">
                            <div className="min-w-0 flex-1">
                                {title && <h2 className="text-lg font-bold">{title}</h2>}
                                {description && <p className="mt-1 text-sm text-ink-2">{description}</p>}
                            </div>
                            <button className="btn btn-ghost -me-2 min-h-9 px-2" onClick={onClose} aria-label="×">
                                <X />
                            </button>
                        </div>
                    )}
                    <div className="overflow-y-auto px-5 py-4">{children}</div>
                    {footer && <div className="flex flex-wrap justify-end gap-2 border-t border-line bg-surface-2 px-5 py-3">{footer}</div>}
                </div>
            )}
        </dialog>
    );
}
