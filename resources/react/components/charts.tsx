import { useMemo, useRef, useState, type ReactNode } from 'react';
import { BarChart3, Table2 } from 'lucide-react';
import { useT } from '@/auth/session';

/**
 * Small hand-drawn SVG charts for the dashboard. Palette: the validated
 * reference categorical slots 1 (blue) and 2 (orange) for two series;
 * status colours only for invoice status, always with the word beside it.
 * Text is always ink, never the series colour.
 */
export const SERIES = ['#2a78d6', '#eb6834'] as const;

/** A chart card with its title, legend and a switch to the table of numbers. */
export function ChartCard({ title, legend, chart, table }: { title: ReactNode; legend?: ReactNode; chart: ReactNode; table: ReactNode }) {
    const t = useT();
    const [asTable, setAsTable] = useState(false);

    return (
        <section className="panel flex flex-col">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
                <h2 className="text-base font-bold">{title}</h2>
                <button className="btn btn-ghost min-h-8 px-2 text-xs" onClick={() => setAsTable((v) => !v)}>
                    {asTable ? <BarChart3 className="size-4!" /> : <Table2 className="size-4!" />}
                    {asTable ? t('spa.ui.vue_graphique') : t('spa.ui.vue_tableau')}
                </button>
            </div>
            <div className="flex-1 p-4">
                {!asTable && legend}
                {asTable ? <div className="max-h-80 overflow-y-auto">{table}</div> : chart}
            </div>
        </section>
    );
}

export function Legend({ items }: { items: { color: string; label: string }[] }) {
    return (
        <div className="mb-3 flex flex-wrap gap-4 text-sm text-ink-2">
            {items.map((i) => (
                <span key={i.label} className="inline-flex items-center gap-2">
                    <span className="h-1 w-4 rounded-full" style={{ background: i.color }} />
                    {i.label}
                </span>
            ))}
        </div>
    );
}

/** Nice round top for the y axis (1, 2, 2.5, 5, 10 × 10^n). */
function niceMax(v: number): number {
    if (v <= 0) return 1;
    const p = 10 ** Math.floor(Math.log10(v));
    for (const m of [1, 2, 2.5, 5, 10]) if (m * p >= v) return m * p;
    return 10 * p;
}

function compact(v: number): string {
    if (Math.abs(v) >= 1_000_000) return `${Number((v / 1_000_000).toFixed(1))}M`;
    if (Math.abs(v) >= 1_000) return `${Number((v / 1_000).toFixed(1))}k`;
    // Small scales (an empty chart spans 0..1) keep their decimals.
    return String(Number(v.toFixed(v < 10 ? 2 : 0)));
}

/** "09/2026" -> "09/26": short, and never mistaken for the year 2020. */
function shortMonth(label: string): string {
    const [m, y] = label.split('/');
    return y ? `${m}/${y.slice(-2)}` : label;
}

interface LineProps {
    labels: string[];
    series: { label: string; color: string; values: number[] }[];
    format: (v: number) => string;
    height?: number;
}

/**
 * Lines over months. Hover (or touch) anywhere shows a crosshair and every
 * series' value for that month; the last point of each line carries a dot.
 */
export function LineChart({ labels, series, format, height = 240 }: LineProps) {
    const ref = useRef<SVGSVGElement>(null);
    const [hover, setHover] = useState<number | null>(null);
    const W = 640;
    const H = height;
    const pad = { t: 12, r: 12, b: 26, l: 44 };
    const max = niceMax(Math.max(0, ...series.flatMap((s) => s.values)));
    const n = labels.length;
    const x = (i: number) => pad.l + (n <= 1 ? 0 : (i * (W - pad.l - pad.r)) / (n - 1));
    const y = (v: number) => pad.t + (1 - v / max) * (H - pad.t - pad.b);
    const ticks = [0, 0.25, 0.5, 0.75, 1].map((f) => f * max);

    const onMove = (clientX: number) => {
        const box = ref.current?.getBoundingClientRect();
        if (!box || n === 0) return;
        const px = ((clientX - box.left) / box.width) * W;
        setHover(Math.max(0, Math.min(n - 1, Math.round(((px - pad.l) / (W - pad.l - pad.r)) * (n - 1)))));
    };

    return (
        <div className="relative" dir="ltr">
            <svg
                ref={ref}
                viewBox={`0 0 ${W} ${H}`}
                className="h-auto w-full touch-none select-none"
                role="img"
                aria-label={series.map((s) => s.label).join(', ')}
                onMouseMove={(e) => onMove(e.clientX)}
                onMouseLeave={() => setHover(null)}
                onTouchMove={(e) => e.touches[0] && onMove(e.touches[0].clientX)}
                onTouchEnd={() => setHover(null)}
            >
                {ticks.map((v) => (
                    <g key={v}>
                        <line x1={pad.l} x2={W - pad.r} y1={y(v)} y2={y(v)} stroke="var(--color-line)" strokeWidth={1} />
                        <text x={pad.l - 8} y={y(v)} textAnchor="end" dominantBaseline="middle" fontSize="11" fill="var(--color-ink-3)">{compact(v)}</text>
                    </g>
                ))}
                {labels.map((l, i) => (i % Math.ceil(n / 6) === 0 || i === n - 1) && (
                    <text key={l} x={x(i)} y={H - 6} textAnchor="middle" fontSize="11" fill="var(--color-ink-3)">{shortMonth(l)}</text>
                ))}
                {series.map((s) => {
                    const d = s.values.map((v, i) => `${i ? 'L' : 'M'}${x(i)},${y(v)}`).join(' ');
                    const last = s.values.length - 1;
                    return (
                        <g key={s.label}>
                            <path d={`${d} L${x(last)},${y(0)} L${x(0)},${y(0)} Z`} fill={s.color} opacity={0.08} />
                            <path d={d} fill="none" stroke={s.color} strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
                            {last >= 0 && <circle cx={x(last)} cy={y(s.values[last] ?? 0)} r={4} fill={s.color} stroke="var(--color-surface)" strokeWidth={2} />}
                        </g>
                    );
                })}
                {hover !== null && (
                    <g pointerEvents="none">
                        <line x1={x(hover)} x2={x(hover)} y1={pad.t} y2={H - pad.b} stroke="var(--color-ink-3)" strokeDasharray="3 3" />
                        {series.map((s) => (
                            <circle key={s.label} cx={x(hover)} cy={y(s.values[hover] ?? 0)} r={4.5} fill={s.color} stroke="var(--color-surface)" strokeWidth={2} />
                        ))}
                    </g>
                )}
            </svg>
            {hover !== null && (
                <div
                    className="pointer-events-none absolute top-2 z-10 min-w-40 rounded-ctl border border-line bg-surface px-3 py-2 text-xs shadow-lift"
                    dir={document.documentElement.dir}
                    style={{ left: `${(x(hover) / W) * 100}%`, transform: hover > n / 2 ? 'translateX(calc(-100% - 12px))' : 'translateX(12px)' }}
                >
                    <p className="mb-1 font-bold text-ink">{labels[hover]}</p>
                    {series.map((s) => (
                        <p key={s.label} className="flex items-center justify-between gap-3 text-ink-2">
                            <span className="inline-flex items-center gap-1.5"><span className="size-2 rounded-full" style={{ background: s.color }} />{s.label}</span>
                            <span className="num font-semibold text-ink">{format(s.values[hover] ?? 0)}</span>
                        </p>
                    ))}
                </div>
            )}
        </div>
    );
}

/** Horizontal bars, one hue, value written at the end of each bar. */
export function BarList({ items, format, empty }: { items: { key: string | number; label: string; value: number }[]; format: (v: number) => string; empty: ReactNode }) {
    const max = useMemo(() => Math.max(0, ...items.map((i) => i.value)), [items]);
    if (items.length === 0) return <>{empty}</>;

    return (
        <ul className="space-y-3">
            {items.map((i) => (
                <li key={i.key} title={`${i.label} — ${format(i.value)}`}>
                    <div className="mb-1 flex items-baseline justify-between gap-3 text-sm">
                        <span className="truncate font-semibold text-ink">{i.label}</span>
                        <span className="num flex-none font-bold text-ink">{format(i.value)}</span>
                    </div>
                    <div className="h-2.5 rounded-full bg-surface-2">
                        <div className="h-full rounded-full" style={{ width: `${max ? Math.max(2, (i.value / max) * 100) : 0}%`, background: SERIES[0] }} />
                    </div>
                </li>
            ))}
        </ul>
    );
}

/** One bar split by share, each part labelled with its word and count. */
export function ShareBar({ parts, empty }: { parts: { key: string; label: string; value: number; color: string }[]; empty: ReactNode }) {
    const total = parts.reduce((s, p) => s + p.value, 0);
    if (total === 0) return <>{empty}</>;

    return (
        <div>
            <div className="flex h-4 gap-0.5 overflow-hidden rounded-full" role="img" aria-label={parts.map((p) => `${p.label} ${p.value}`).join(', ')}>
                {parts.map((p) => <div key={p.key} title={`${p.label}: ${p.value}`} style={{ width: `${(p.value / total) * 100}%`, background: p.color }} />)}
            </div>
            <ul className="mt-4 space-y-2">
                {parts.map((p) => (
                    <li key={p.key} className="flex items-center justify-between gap-3 text-sm">
                        <span className="inline-flex items-center gap-2 font-semibold text-ink"><span className="size-3 rounded-sm" style={{ background: p.color }} />{p.label}</span>
                        <span className="num text-ink-2"><b className="text-ink">{p.value}</b> · {Math.round((p.value / total) * 100)}%</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
