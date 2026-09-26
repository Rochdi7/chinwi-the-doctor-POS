/**
 * The one way the React app talks to Laravel.
 *
 * Requests ride on the Laravel session cookie; CSRF is the standard
 * XSRF-TOKEN cookie echoed back as the X-XSRF-TOKEN header, exactly what
 * Laravel's VerifyCsrfToken expects.
 */

export type ApiErrorKind = 'network' | 'validation' | 'auth' | 'csrf' | 'conflict' | 'notfound' | 'server';

export class ApiError extends Error {
    constructor(
        readonly kind: ApiErrorKind,
        readonly status: number,
        message: string,
        readonly errors: Record<string, string[]> = {},
        readonly data: unknown = null,
    ) {
        super(message);
        this.name = 'ApiError';
    }

    /** First validation message, already translated by Laravel. */
    firstError(): string | null {
        const first = Object.values(this.errors)[0];
        return first?.[0] ?? null;
    }
}

interface RequestOptions {
    method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    body?: unknown;
    query?: Record<string, string | number | null | undefined>;
    signal?: AbortSignal;
}

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match?.[1] ? decodeURIComponent(match[1]) : null;
}

function kindFor(status: number): ApiErrorKind {
    switch (status) {
        case 401:
        case 403:
            return 'auth';
        case 404:
            return 'notfound';
        case 409:
            return 'conflict';
        case 419:
            return 'csrf';
        case 422:
            return 'validation';
        default:
            return 'server';
    }
}

export async function api<T>(path: string, options: RequestOptions = {}): Promise<T> {
    const url = new URL('/api' + path, window.location.origin);

    for (const [key, value] of Object.entries(options.query ?? {})) {
        if (value !== null && value !== undefined && value !== '') {
            url.searchParams.set(key, String(value));
        }
    }

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    const token = xsrfToken();
    if (token) headers['X-XSRF-TOKEN'] = token;
    if (options.body !== undefined) headers['Content-Type'] = 'application/json';

    let response: Response;

    try {
        response = await fetch(url, {
            method: options.method ?? 'GET',
            credentials: 'same-origin',
            headers,
            body: options.body === undefined ? undefined : JSON.stringify(options.body),
            signal: options.signal,
        });
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') throw error;
        throw new ApiError('network', 0, 'network');
    }

    const data: unknown = await response.json().catch(() => null);

    if (response.ok) return data as T;

    const body = (data ?? {}) as { message?: string; errors?: Record<string, string[]> };
    const kind = kindFor(response.status);

    // Technical details go to the console, never to the cashier.
    if (kind === 'server') console.error('[api]', response.status, path, data);

    throw new ApiError(kind, response.status, body.message ?? '', body.errors ?? {}, data);
}

/** What the cashier reads when something goes wrong. */
export function errorMessage(error: unknown, t: (key: string) => string): string {
    if (!(error instanceof ApiError)) return t('spa.erreur.serveur');

    switch (error.kind) {
        case 'network':
            return t('spa.erreur.reseau');
        case 'validation':
            return error.firstError() ?? t('spa.erreur.validation');
        case 'auth':
        case 'csrf':
            return t('spa.erreur.session');
        case 'conflict':
        case 'notfound':
            return error.message || t('spa.erreur.serveur');
        default:
            return t('spa.erreur.serveur');
    }
}
