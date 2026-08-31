<x-filament-widgets::widget>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->stats as $key => $stat)
            <button
                type="button"
                wire:click="openDetail('{{ $key }}')"
                wire:key="stat-{{ $key }}"
                class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 text-start shadow-sm ring-1 ring-gray-950/5 transition duration-75 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-600 dark:bg-gray-900 dark:ring-white/10 dark:hover:bg-white/5"
            >
                <div class="flex items-center gap-x-2">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $stat['label'] }}
                    </span>

                    <x-filament::icon
                        :icon="$stat['icon']"
                        @class([
                            'h-4 w-4',
                            match ($stat['color']) {
                                'success' => 'text-success-500',
                                'danger' => 'text-danger-500',
                                'warning' => 'text-warning-500',
                                default => 'text-info-500',
                            },
                        ])
                    />
                </div>

                <div class="mt-1 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                    {{ number_format($stat['value'], 2) }} DH
                </div>

                <div class="mt-2 flex items-center gap-x-1 text-xs text-gray-400 dark:text-gray-500">
                    <x-filament::icon icon="heroicon-m-cursor-arrow-rays" class="h-3.5 w-3.5" />
                    {{ __('app.stats.voir_details') }}
                </div>
            </button>
        @endforeach
    </div>

    <x-filament::modal
        id="stats-detail"
        width="7xl"
        slide-over
        :close-by-clicking-away="true"
        @close-modal.window="$wire.closeDetail()"
    >
        <x-slot name="heading">
            {{ $this->detail ? $this->stats[$this->detail]['label'] : '' }}
        </x-slot>

        <x-slot name="description">
            @if ($this->detail)
                {{ __('app.stats.total') }} :
                <span class="font-semibold">{{ number_format($this->stats[$this->detail]['value'], 2) }} DH</span>
                &mdash; {{ $this->detailRows['count'] ?? count($this->detailRows['rows']) }} {{ __('app.stats.lignes') }}
            @endif
        </x-slot>

        @if ($this->detail)
            @php($detail = $this->detailRows)

            @if (count($detail['rows']))
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-white/10">
                                @foreach ($detail['columns'] as $column)
                                    <th class="whitespace-nowrap px-3 py-2 text-start font-medium text-gray-500 dark:text-gray-400">
                                        {{ $column }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($detail['rows'] as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    @foreach ($row['cells'] as $index => $cell)
                                        <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-white">
                                            @if ($index === 0 && $row['url'])
                                                <a href="{{ $row['url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                                    {{ $cell }}
                                                </a>
                                            @else
                                                {{ $cell }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-200 font-semibold dark:border-white/10">
                                <td class="px-3 py-2" colspan="{{ max(count($detail['columns']) - 1, 1) }}">
                                    {{ __('app.stats.total') }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-2 text-gray-950 dark:text-white">
                                    {{ number_format($detail['total'], 2) }} DH
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if (($this->detailRows['count'] ?? 0) > count($detail['rows']))
                    <p class="pt-3 text-center text-xs text-gray-500 dark:text-gray-400">
                        {{ __('app.stats.tronque', [
                            'affichees' => count($detail['rows']),
                            'total' => $this->detailRows['count'],
                        ]) }}
                    </p>
                @endif
            @else
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    {{ __('app.stats.aucune_ligne') }}
                </p>
            @endif
        @endif
    </x-filament::modal>
</x-filament-widgets::widget>
