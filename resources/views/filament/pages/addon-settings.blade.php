<x-filament-panels::page>

    <div class="fi-ta-ctn rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 overflow-hidden">
        <table class="w-full text-sm divide-y divide-gray-200 dark:divide-white/10">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Послуга
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Тип
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Ціна
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Тривалість
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        Статус
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($this->getAddons() as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                        {{ $row['label'] }}
                    </td>
                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400 font-mono text-xs">
                        {{ $row['addon']->value }}
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                        {{ number_format($row['price'], 0, '.', ' ') }} ₴
                    </td>
                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">
                        {{ $row['days'] }} днів
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($row['status'] === 'Активна')
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                Активна
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                В розробці
                            </span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-filament::section>
        <x-slot name="heading">Примітка</x-slot>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Ціни та тривалість додаткових послуг задані в коді (<code class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">App\Enums\AddonType</code>).
            Для зміни зверніться до розробника.
        </p>
    </x-filament::section>

</x-filament-panels::page>
