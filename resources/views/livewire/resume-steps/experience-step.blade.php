<div class="px-6 py-6 space-y-6">
    <div>
        <h2 class="text-xl font-bold text-gray-900">Досвід роботи</h2>
        <p class="text-sm text-gray-600 mt-1">Додайте ваші минулі та поточні посади (макс. 5)</p>
    </div>

    @if (isset($errors['general']))
        <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ $errors['general'] }}</div>
    @endif

    {{-- Experience list --}}
    @if (!empty($experiences))
        <div class="space-y-3">
            @foreach ($experiences as $exp)
                <div class="p-4 border border-gray-200 rounded-lg">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $exp['position'] }}</p>
                            <p class="text-sm text-gray-600">{{ $exp['company_name'] }}
                                @if ($exp['company_industry'])
                                    · <span class="text-gray-400">{{ $exp['company_industry'] }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ \Carbon\Carbon::parse($exp['start_date'])->format('M Y') }} —
                                @if ($exp['is_current'])
                                    <span class="text-green-600 font-semibold">Поточна посада</span>
                                @else
                                    {{ \Carbon\Carbon::parse($exp['end_date'])->format('M Y') }}
                                @endif
                            </p>
                        </div>
                        <button
                            wire:click="deleteExperience({{ $exp['id'] }})"
                            wire:confirm="Видалити цей запис?"
                            class="text-red-400 hover:text-red-600 text-lg leading-none"
                        >✕</button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="p-6 bg-gray-50 border border-gray-200 rounded-lg text-center text-sm text-gray-500">
            Досвід роботи не додано
        </div>
    @endif

    {{-- Add form --}}
    @if ($isAddingNew || empty($experiences))
        <div class="border-t border-gray-200 pt-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-900">
                {{ empty($experiences) ? 'Додайте перший досвід' : 'Новий запис' }}
            </h3>

            {{-- Position search --}}
            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">Посада</label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="positionSearch"
                    wire:blur="closeSuggestions"
                    placeholder="Senior Laravel Developer"
                    autocomplete="off"
                    class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                        {{ isset($errors['position']) ? 'border-red-500' : 'border-gray-300' }}"
                />
                @if ($showSuggestions)
                    <ul class="absolute z-50 left-0 right-0 mt-1 bg-gray-800 border border-gray-700 rounded-lg shadow-lg max-h-52 overflow-y-auto">
                        @foreach ($positionSuggestions as $suggestion)
                            <li
                                wire:mousedown.prevent="selectPosition('{{ addslashes($suggestion) }}')"
                                class="px-4 py-2 text-sm text-gray-200 hover:bg-gray-700 cursor-pointer"
                            >{{ $suggestion }}</li>
                        @endforeach
                    </ul>
                @endif
                @if (isset($errors['position']))
                    <p class="mt-1 text-sm text-red-600">{{ $errors['position'] }}</p>
                @endif
            </div>

            {{-- Company --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Назва компанії</label>
                <input type="text" wire:model="newExperience.company_name" placeholder="TechCorp"
                    class="w-full px-4 py-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                        {{ isset($errors['company_name']) ? 'border-red-500' : 'border-gray-300' }}" />
                @if (isset($errors['company_name']))
                    <p class="mt-1 text-sm text-red-600">{{ $errors['company_name'] }}</p>
                @endif
            </div>

            {{-- Industry search --}}
            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">Галузь <span class="text-gray-400">(необов'язково)</span></label>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="industrySearch"
                    wire:blur="closeIndustrySuggestions"
                    placeholder="Наприклад: IT, комп'ютери, інтернет"
                    autocomplete="off"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                @if ($showIndustrySuggestions)
                    <ul class="absolute z-50 left-0 right-0 mt-1 bg-gray-800 border border-gray-700 rounded-lg shadow-lg max-h-52 overflow-y-auto">
                        @foreach ($industrySuggestions as $suggestion)
                            <li
                                wire:mousedown.prevent="selectIndustry('{{ addslashes($suggestion) }}')"
                                class="px-4 py-2 text-sm text-gray-200 hover:bg-gray-700 cursor-pointer"
                            >{{ $suggestion }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Start date --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Дата початку</label>
                <input type="date" wire:model="newExperience.start_date"
                    class="mj-date-input w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                        {{ isset($errors['start_date']) ? 'border-red-500' : '' }}" />
                @if (isset($errors['start_date']))
                    <p class="mt-1 text-sm text-red-600">{{ $errors['start_date'] }}</p>
                @endif
            </div>

            {{-- Is current --}}
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox"
                    wire:click="toggleCurrentJob"
                    @checked($newExperience['is_current'])
                    class="w-4 h-4 rounded border-gray-300" />
                <span class="text-sm text-gray-700">Я працюю тут зараз</span>
            </label>

            {{-- End date --}}
            @if (!$newExperience['is_current'])
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Дата закінчення</label>
                    <input type="date" wire:model="newExperience.end_date"
                        class="mj-date-input w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                            {{ isset($errors['end_date']) ? 'border-red-500' : '' }}" />
                    @if (isset($errors['end_date']))
                        <p class="mt-1 text-sm text-red-600">{{ $errors['end_date'] }}</p>
                    @endif
                </div>
            @endif

            <div class="flex gap-3 pt-2">
                <button wire:click="addExperience"
                    class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold transition">
                    Додати
                </button>
                @if (!empty($experiences))
                    <button wire:click="$set('isAddingNew', false)"
                        class="flex-1 px-4 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 font-semibold transition">
                        Скасувати
                    </button>
                @endif
            </div>
        </div>

    @elseif ($canAddMore)
        <button wire:click="$set('isAddingNew', true)"
            class="w-full px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg text-gray-600
                hover:border-blue-500 hover:text-blue-600 font-semibold transition">
            + Додати ще один досвід
        </button>
    @endif

    <div class="mj-alert p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
        <p class="text-sm text-yellow-800">
            <strong>Порада:</strong> Більше досвіду = більше шансів отримати відгук від роботодавця. Мінімум одна посада допоможе роботодавцям зрозуміти ваш рівень.
        </p>
    </div>
</div>
