import { ImageIcon } from 'lucide-react';

/**
 * A product photo in a soft grey box, or the same box with the generic image
 * mark when there is none: the tile grid keeps its rhythm either way. The
 * caller sets the size (size-10, aspect-square w-full...).
 */
export function ProductPhoto({ src, className = '', iconClassName = 'size-8' }: { src: string | null | undefined; className?: string; iconClassName?: string }) {
    return (
        <span className={`grid flex-none place-items-center overflow-hidden rounded-xl bg-surface-2 text-ink-3 ${className}`}>
            {src ? <img src={src} alt="" loading="lazy" decoding="async" className="size-full object-contain p-[6%]" /> : <ImageIcon className={iconClassName} strokeWidth={1.75} />}
        </span>
    );
}
