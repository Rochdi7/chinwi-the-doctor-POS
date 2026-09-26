import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, errorMessage } from '@/lib/api';
import { toast } from '@/components/ui/toast';
import { useT } from '@/auth/session';
import type { CategoryRow, ClientOption, Paginated } from '@/types/api';

/** A paginated list from the API; the previous page stays while the next loads. */
export function useList<T>(path: string, params: Record<string, string>) {
    return useQuery<Paginated<T>>({
        queryKey: [path, params],
        queryFn: ({ signal }) => api<Paginated<T>>(path, { query: params, signal }),
        placeholderData: keepPreviousData,
    });
}

export function useCategories() {
    return useQuery({
        queryKey: ['/categories'],
        queryFn: () => api<{ data: CategoryRow[] }>('/categories').then((r) => r.data),
        staleTime: 60_000,
    });
}

export function useClientOptions() {
    return useQuery({
        queryKey: ['/clients/options'],
        queryFn: () => api<{ data: ClientOption[] }>('/clients/options').then((r) => r.data),
        staleTime: 60_000,
    });
}

/**
 * A write to the API: toast on success, the cashier-safe message on
 * failure, and the listed query keys refreshed.
 */
export function useSave<TInput, TResult = unknown>(
    run: (input: TInput) => Promise<TResult>,
    options: { invalidate: string[]; success?: string; onSuccess?: (result: TResult) => void; silentErrors?: boolean },
) {
    const queryClient = useQueryClient();
    const t = useT();

    return useMutation({
        mutationFn: run,
        onSuccess: (result) => {
            for (const key of options.invalidate) void queryClient.invalidateQueries({ queryKey: [key] });
            toast.success(options.success ?? t('spa.ui.enregistre'));
            options.onSuccess?.(result);
        },
        onError: (error) => {
            // Validation errors are shown beside their fields by the form.
            if (!options.silentErrors) toast.error(errorMessage(error, t));
        },
    });
}
