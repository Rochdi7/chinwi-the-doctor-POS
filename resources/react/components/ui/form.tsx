import { useId, type InputHTMLAttributes, type ReactNode, type TextareaHTMLAttributes } from 'react';
import { Combobox } from './Combobox';

interface FieldShellProps {
    label: ReactNode;
    error?: string | null;
    hint?: ReactNode;
    children: (id: string) => ReactNode;
    className?: string;
}

/** Label, control, hint or error: one layout for every form field. */
export function Field({ label, error, hint, children, className = '' }: FieldShellProps) {
    const id = useId();

    return (
        <div className={`space-y-1.5 ${className}`}>
            <label htmlFor={id} className="block text-sm font-semibold text-ink">{label}</label>
            {children(id)}
            {error ? (
                <p role="alert" className="text-sm font-medium text-bad">{error}</p>
            ) : hint ? (
                <p className="text-xs text-ink-3">{hint}</p>
            ) : null}
        </div>
    );
}

type InputProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange' | 'value'> & {
    label: ReactNode;
    value: string | number | null | undefined;
    onChange: (value: string) => void;
    error?: string | null;
    hint?: ReactNode;
    suffix?: ReactNode;
};

export function TextField({ label, value, onChange, error, hint, suffix, className, ...rest }: InputProps) {
    return (
        <Field label={label} error={error} hint={hint} className={className}>
            {(id) => (
                <div className="relative flex items-center">
                    <input
                        id={id}
                        className={`field ${suffix ? 'pe-14' : ''} ${error ? 'border-bad focus:border-bad' : ''}`}
                        value={value ?? ''}
                        onChange={(e) => onChange(e.target.value)}
                        aria-invalid={!!error}
                        {...rest}
                    />
                    {suffix && <span className="pointer-events-none absolute end-3 text-sm font-semibold text-ink-3">{suffix}</span>}
                </div>
            )}
        </Field>
    );
}

interface SelectProps {
    label: ReactNode;
    value: string | number | null | undefined;
    onChange: (value: string) => void;
    options: { value: string | number; label: string }[];
    /** With a placeholder the choice can be cleared back to nothing. */
    placeholder?: string;
    error?: string | null;
    hint?: ReactNode;
    className?: string;
}

export function SelectField({ label, value, onChange, options, placeholder, error, hint, className }: SelectProps) {
    return (
        <Field label={label} error={error} hint={hint} className={className}>
            {(id) => (
                <Combobox
                    id={id}
                    value={value === null || value === undefined ? '' : String(value)}
                    onChange={onChange}
                    options={options.map((o) => ({ value: String(o.value), label: o.label }))}
                    placeholder={placeholder}
                    clearable={placeholder !== undefined}
                    invalid={!!error}
                />
            )}
        </Field>
    );
}

type AreaProps = Omit<TextareaHTMLAttributes<HTMLTextAreaElement>, 'onChange' | 'value'> & {
    label: ReactNode;
    value: string | null | undefined;
    onChange: (value: string) => void;
    error?: string | null;
};

export function TextArea({ label, value, onChange, error, className, ...rest }: AreaProps) {
    return (
        <Field label={label} error={error} className={className}>
            {(id) => (
                <textarea id={id} className={`field min-h-20 py-2.5 ${error ? 'border-bad' : ''}`} value={value ?? ''} onChange={(e) => onChange(e.target.value)} rows={2} {...rest} />
            )}
        </Field>
    );
}

/** An on/off switch with its label; a real checkbox underneath. */
export function Toggle({ label, checked, onChange, hint }: { label: ReactNode; checked: boolean; onChange: (v: boolean) => void; hint?: ReactNode }) {
    return (
        <label className="flex cursor-pointer items-center justify-between gap-4 rounded-ctl border border-line bg-surface-2 px-3 py-2.5">
            <span>
                <span className="block text-sm font-semibold">{label}</span>
                {hint && <span className="block text-xs text-ink-3">{hint}</span>}
            </span>
            <input type="checkbox" className="peer sr-only" checked={checked} onChange={(e) => onChange(e.target.checked)} />
            <span className="relative h-6 w-11 flex-none rounded-full bg-line-strong transition-colors peer-checked:bg-ok peer-focus-visible:ring-3 peer-focus-visible:ring-brand/30 after:absolute after:top-0.5 after:start-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-5 rtl:peer-checked:after:-translate-x-5" />
        </label>
    );
}

/** A plain checkbox with its label, for a one-off choice (Toggle is for settings). */
export function Checkbox({ label, checked, onChange }: { label: ReactNode; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-2">
            <input type="checkbox" className="size-4 accent-brand" checked={checked} onChange={(e) => onChange(e.target.checked)} />
            {label}
        </label>
    );
}

/** Laravel's 422 errors, first message per field. */
export function fieldErrors(errors: Record<string, string[]> | undefined): Record<string, string> {
    return Object.fromEntries(Object.entries(errors ?? {}).map(([k, v]) => [k, v[0] ?? '']));
}
