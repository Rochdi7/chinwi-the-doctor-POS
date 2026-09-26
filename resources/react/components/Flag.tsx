import type { ReactNode } from 'react';

/**
 * Flags for the language menu, drawn inline: Windows has no flag emoji, and
 * the till must not depend on an image request. Arabic and Darija both show
 * Morocco; their labels tell them apart.
 */
const FLAGS: Record<string, ReactNode> = {
    fr: (
        <>
            <rect width="300" height="600" fill="#002654" />
            <rect x="300" width="300" height="600" fill="#ffffff" />
            <rect x="600" width="300" height="600" fill="#ce1126" />
        </>
    ),
    ma: (
        <>
            <rect width="900" height="600" fill="#c1272d" />
            {/* The star's line is thicker than the official one so it survives at icon size. */}
            <path fill="none" stroke="#006233" strokeWidth="34" strokeLinejoin="round" d="M450 205 382 413 559 285H341l177 128z" />
        </>
    ),
};

const BY_LOCALE: Record<string, keyof typeof FLAGS> = { fr: 'fr', ar: 'ma', ary: 'ma' };

export function Flag({ locale, className = '' }: { locale: string; className?: string }) {
    const flag = FLAGS[BY_LOCALE[locale] ?? ''];
    if (!flag) return null;

    return (
        <svg viewBox="0 0 900 600" aria-hidden className={`h-3.5 w-[1.3125rem] flex-none overflow-hidden rounded-[3px] ring-1 ring-black/10 ${className}`}>
            {flag}
        </svg>
    );
}
