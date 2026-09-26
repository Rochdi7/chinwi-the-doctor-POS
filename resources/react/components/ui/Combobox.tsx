import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Check, ChevronDown, Search, X } from 'lucide-react';
import { useT } from '@/auth/session';

export interface Option {
    value: string;
    label: string;
    icon?: ReactNode;
}

interface Props {
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    /** Shown when nothing is chosen; also the "clear" choice when clearable. */
    placeholder?: string;
    clearable?: boolean;
    disabled?: boolean;
    icon?: ReactNode;
    className?: string;
    id?: string;
    'aria-label'?: string;
    /** Compact: a filter in a toolbar rather than a form field. */
    size?: 'md' | 'sm';
    invalid?: boolean;
}

/**
 * One dropdown for the whole app, instead of the browser's own <select>
 * (whose list floats away from its field inside a dialog). Big rows, a
 * search box once there are more than a few choices, arrow keys, Enter,
 * Escape. Opens upward when there is no room below.
 */
export function Combobox({ value, onChange, options, placeholder = '—', clearable = false, disabled, icon, className = '', id, size = 'md', invalid, ...rest }: Props) {
    const t = useT();
    const autoId = useId();
    const buttonId = id ?? autoId;
    const button = useRef<HTMLButtonElement>(null);
    const panel = useRef<HTMLDivElement>(null);
    const search = useRef<HTMLInputElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const [pos, setPos] = useState<{ top: number; left: number; width: number; up: boolean }>({ top: 0, left: 0, width: 0, up: false });

    const selected = options.find((o) => o.value === value);
    // Select2-style: a search box as soon as there is anything to search.
    const searchable = options.length > 3;
    const list = useMemo(() => {
        const q = query.trim().toLowerCase();
        const base = q ? options.filter((o) => o.label.toLowerCase().includes(q)) : options;
        return clearable && !q ? [{ value: '', label: placeholder }, ...base] : base;
    }, [options, query, clearable, placeholder]);

    // Place the panel under (or above) the button, fixed to the viewport so
    // a scrolling dialog never clips it.
    useLayoutEffect(() => {
        if (!open || !button.current) return;
        const r = button.current.getBoundingClientRect();
        const wanted = Math.min(320, 48 + list.length * 44 + (searchable ? 48 : 0));
        const up = r.bottom + wanted > window.innerHeight - 8 && r.top > wanted;
        setPos({ top: up ? r.top - 4 : r.bottom + 4, left: r.left, width: r.width, up });
    }, [open, list.length, searchable]);

    useEffect(() => {
        if (!open) return;
        setQuery('');
        setActive(Math.max(0, list.findIndex((o) => o.value === value)));
        const t0 = window.setTimeout(() => search.current?.focus(), 0);
        const onDown = (e: MouseEvent) => {
            if (!panel.current?.contains(e.target as Node) && !button.current?.contains(e.target as Node)) setOpen(false);
        };
        const onScroll = () => setOpen(false);
        document.addEventListener('mousedown', onDown);
        window.addEventListener('resize', onScroll);
        return () => {
            window.clearTimeout(t0);
            document.removeEventListener('mousedown', onDown);
            window.removeEventListener('resize', onScroll);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const choose = (v: string) => {
        onChange(v);
        setOpen(false);
        button.current?.focus();
    };

    const onKey = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min(list.length - 1, a + 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(0, a - 1)); }
        else if (e.key === 'Enter') { e.preventDefault(); const o = list[active]; if (o) choose(o.value); }
        else if (e.key === 'Escape') { e.preventDefault(); setOpen(false); button.current?.focus(); }
    };

    const h = size === 'sm' ? 'min-h-10 text-sm' : 'min-h-11';

    return (
        <>
            <button
                ref={button}
                id={buttonId}
                type="button"
                disabled={disabled}
                aria-haspopup="listbox"
                aria-expanded={open}
                aria-label={rest['aria-label']}
                aria-invalid={invalid}
                onClick={() => setOpen((o) => !o)}
                onKeyDown={(e) => { if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setOpen(true); } }}
                className={`field flex ${h} items-center gap-2 text-start ${invalid ? 'border-bad' : ''} ${open ? 'border-brand ring-3 ring-brand/20' : ''} ${className}`}
            >
                {(selected?.icon ?? icon) && <span className="flex-none text-ink-3">{selected?.icon ?? icon}</span>}
                <span className={`min-w-0 flex-1 truncate ${selected ? 'font-semibold' : 'text-ink-3'}`}>{selected?.label ?? placeholder}</span>
                {clearable && selected ? (
                    <span role="button" aria-label="×" className="grid size-6 flex-none place-items-center rounded-full text-ink-3 hover:bg-line hover:text-ink" onClick={(e) => { e.stopPropagation(); choose(''); }}>
                        <X className="size-3.5" />
                    </span>
                ) : (
                    <ChevronDown className={`size-4 flex-none text-ink-3 transition-transform ${open ? 'rotate-180' : ''}`} />
                )}
            </button>

            {open && (
                <div
                    ref={panel}
                    role="listbox"
                    onKeyDown={onKey}
                    className="fixed z-[100] flex flex-col overflow-hidden rounded-card border border-line bg-surface shadow-lift"
                    style={{ left: pos.left, width: Math.max(pos.width, 220), maxHeight: 320, ...(pos.up ? { bottom: window.innerHeight - pos.top } : { top: pos.top }) }}
                >
                    {searchable && (
                        <label className="relative flex items-center border-b border-line p-2">
                            <Search className="pointer-events-none absolute start-4 size-4 text-ink-3" />
                            <input ref={search} className="field min-h-10 bg-surface-2 ps-9 text-sm" value={query} onChange={(e) => { setQuery(e.target.value); setActive(0); }} placeholder={t('spa.ui.rechercher')} />
                        </label>
                    )}
                    {!searchable && <input ref={search} className="sr-only" aria-hidden readOnly />}
                    <ul className="overflow-y-auto py-1">
                        {list.length === 0 && <li className="px-3 py-3 text-sm text-ink-3">{t('spa.ui.aucun_resultat')}</li>}
                        {list.map((o, i) => {
                            const isSel = o.value === value;
                            return (
                                <li
                                    key={o.value || '__empty'}
                                    role="option"
                                    aria-selected={isSel}
                                    onMouseEnter={() => setActive(i)}
                                    onClick={() => choose(o.value)}
                                    className={`flex min-h-11 cursor-pointer items-center gap-2 px-3 text-[0.95rem] ${i === active ? 'bg-brand-soft text-brand' : ''} ${isSel ? 'font-bold' : ''} ${o.value === '' ? 'text-ink-3' : ''}`}
                                >
                                    {o.icon}
                                    <span className="min-w-0 flex-1 truncate">{o.label}</span>
                                    {isSel && <Check className="size-4 flex-none" />}
                                </li>
                            );
                        })}
                    </ul>
                </div>
            )}
        </>
    );
}
