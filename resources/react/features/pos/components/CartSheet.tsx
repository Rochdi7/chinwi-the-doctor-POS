import { useEffect, useRef, type ReactNode } from 'react';

interface Props {
    open: boolean;
    onClose: () => void;
    children: ReactNode;
}

/**
 * The order as a bottom sheet, for a phone or a portrait tablet where there
 * is no room for it beside the products. A native <dialog>, as in Dialog:
 * Escape, the backdrop and the focus trap come from the browser.
 */
export function CartSheet({ open, onClose, children }: Props) {
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
                if (e.target === ref.current) onClose();
            }}
            className="m-0 mt-auto h-auto max-h-[92dvh] w-full max-w-none overflow-hidden rounded-t-2xl border-0 bg-surface p-0 text-ink shadow-lift"
        >
            {open && (
                <div className="flex max-h-[92dvh] animate-sheet flex-col">
                    <span className="mx-auto mt-2 mb-1 h-1.5 w-12 flex-none rounded-full bg-line-strong" aria-hidden />
                    {children}
                </div>
            )}
        </dialog>
    );
}
