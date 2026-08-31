@php
    /** @var \App\Models\ActivityLog $record */
    $rows = \App\Support\AuditDiff::rows($record);
@endphp

@if (empty($rows))
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.log.aucun_changement') }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10 text-left">
                    <th class="py-2 pr-4 font-medium">{{ __('app.log.champ') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('app.log.avant') }}</th>
                    <th class="py-2 pr-4 font-medium">{{ __('app.log.apres') }}</th>
                    <th class="py-2 font-medium">{{ __('app.log.difference') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-gray-100 dark:border-white/5 last:border-0">
                        <td class="py-2 pr-4 font-medium">{{ $row['champ'] }}</td>
                        <td class="py-2 pr-4 text-gray-500 dark:text-gray-400 line-through decoration-gray-300">{{ $row['avant'] }}</td>
                        <td class="py-2 pr-4 font-semibold">{{ $row['apres'] }}</td>
                        <td class="py-2 whitespace-nowrap {{ str_starts_with((string) $row['delta'], '+') ? 'text-success-600' : 'text-danger-600' }}">
                            {{ $row['delta'] ?? '' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
