<?php

declare(strict_types=1);

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {};
?>

<div class="min-h-screen bg-gray-50 dark:bg-gray-900">
<x-seeker-tabs />
<div class="max-w-[860px] mx-auto px-4 py-8 space-y-6">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-[#2AABEE]/10 flex items-center justify-center">
            <svg class="w-5 h-5 text-[#2AABEE]" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/>
            </svg>
        </div>
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Telegram-бот для кандидатів</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Отримуйте сповіщення про нові вакансії та статуси заявок</p>
        </div>
    </div>

    {{-- Step 1 — Link account --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold">1</span>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Прив'язати Telegram-акаунт</h2>
        </div>
        <div class="px-5 py-4 space-y-3">
            <ol class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                <li class="flex gap-3">
                    <span class="flex-shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">①</span>
                    <span>Відкрийте розділ <a href="{{ route('seeker.profile') }}" class="text-blue-600 hover:underline font-medium">Мій профіль</a> у кабінеті кандидата.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex-shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">②</span>
                    <span>У блоці <strong class="font-medium text-gray-900 dark:text-white">«Telegram»</strong> натисніть <strong class="font-medium text-gray-900 dark:text-white">«Прив'язати Telegram»</strong>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex-shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">③</span>
                    <span>Відкриється бот <strong class="font-medium text-gray-900 dark:text-white">@myjob_in_bot</strong>. Натисніть кнопку <strong class="font-medium text-gray-900 dark:text-white">«Поділитися контактом»</strong>.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex-shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">④</span>
                    <span>Сторінка профілю оновиться автоматично — статус зміниться на <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-green-50 text-green-700 text-xs font-medium">Прив'язано</span>.</span>
                </li>
            </ol>
            <div class="mt-4 flex">
                <a href="https://t.me/myjob_in_bot" target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-[#2AABEE] hover:bg-[#229ED9] text-white text-sm font-medium rounded-xl transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/>
                    </svg>
                    Відкрити @myjob_in_bot
                </a>
            </div>
        </div>
    </div>

    {{-- Step 2 — Notification channels --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold">2</span>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Налаштувати канал сповіщень</h2>
        </div>
        <div class="px-5 py-4 space-y-3">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                У розділі <a href="{{ route('seeker.profile') }}" class="text-blue-600 hover:underline font-medium">Мій профіль</a> → <strong class="font-medium text-gray-900 dark:text-white">«Канал сповіщень»</strong> оберіть спосіб отримання повідомлень:
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-600">
                    <span class="text-lg leading-none mt-0.5">✉️</span>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Email</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Листи на вказану адресу. Увімкнено за замовчуванням.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-600">
                    <svg class="w-5 h-5 text-[#2AABEE] flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.833.941z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Telegram</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Миттєві повідомлення в месенджер. Потрібна прив'язка.</p>
                    </div>
                </div>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500">Можна увімкнути обидва канали одночасно.</p>
        </div>
    </div>

    {{-- Step 3 — Job alerts --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold">3</span>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Підписатися на нові вакансії</h2>
        </div>
        <div class="px-5 py-4 space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Бот може сповіщати вас щогодини про нові вакансії у вибраних категоріях.
            </p>
            <ol class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                <li class="flex gap-3">
                    <span class="flex-shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">①</span>
                    <span>Відкрийте <strong class="font-medium text-gray-900 dark:text-white">@myjob_in_bot</strong> у Telegram.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex-shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">②</span>
                    <span>Надішліть команду <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-xs font-mono">/alerts</code> — бот покаже список усіх категорій.</span>
                </li>
                <li class="flex gap-3">
                    <span class="flex-shrink-0 mt-0.5 text-gray-400 dark:text-gray-500">③</span>
                    <span>Натисніть на потрібні категорії, щоб підписатися. Активні підписки позначені <strong class="font-medium text-gray-900 dark:text-white">✅</strong>. Повторний натиск — скасовує підписку.</span>
                </li>
            </ol>

            {{-- Mock alerts menu --}}
            <div class="bg-gray-50 dark:bg-gray-700/40 rounded-xl p-3 max-w-xs border border-gray-200 dark:border-gray-600 font-mono text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                <p>🔔 <b>Job Alerts</b></p>
                <p class="mt-1 text-gray-500 dark:text-gray-400">Select categories to receive notifications:</p>
                <div class="mt-2 space-y-1">
                    <div class="px-2 py-1 bg-[#2AABEE] text-white rounded">✅ IT / Розробка</div>
                    <div class="px-2 py-1 bg-[#2AABEE] text-white rounded">Маркетинг</div>
                    <div class="px-2 py-1 bg-[#2AABEE] text-white rounded">✅ Дизайн</div>
                    <div class="px-2 py-1 bg-[#2AABEE] text-white rounded">Продажі</div>
                </div>
            </div>

            {{-- Mock new vacancy notification --}}
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Приклад сповіщення про нову вакансію:</p>
                <div class="bg-gray-50 dark:bg-gray-700/40 rounded-xl p-3 max-w-sm border border-gray-200 dark:border-gray-600 font-mono text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                    <p>🆕 <b>Нова вакансія у категорії IT / Розробка</b></p>
                    <p class="mt-1">📌 <b>Senior PHP Developer</b></p>
                    <p>🏭 TechCorp · Київ</p>
                    <p>💰 80 000–120 000 UAH</p>
                    <div class="mt-2 inline-block px-3 py-1 bg-[#2AABEE] text-white rounded-lg">👉 Переглянути вакансію</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Step 4 — Application status --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold">4</span>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Сповіщення про статус заявки</h2>
        </div>
        <div class="px-5 py-4 space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Коли роботодавець змінює статус вашої заявки — бот миттєво надсилає повідомлення.
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs text-center">
                @foreach([
                    ['emoji' => '📋', 'label' => 'Розгляд', 'color' => 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'],
                    ['emoji' => '🔍', 'label' => 'Скринінг', 'color' => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300'],
                    ['emoji' => '🤝', 'label' => 'Співбесіда', 'color' => 'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300'],
                    ['emoji' => '🎉', 'label' => 'Прийнято', 'color' => 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300'],
                ] as $s)
                    <div class="flex flex-col items-center gap-1 p-2 rounded-xl {{ $s['color'] }}">
                        <span class="text-lg">{{ $s['emoji'] }}</span>
                        <span class="font-medium">{{ $s['label'] }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Mock status notification --}}
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Приклад повідомлення:</p>
                <div class="bg-gray-50 dark:bg-gray-700/40 rounded-xl p-3 max-w-sm border border-gray-200 dark:border-gray-600 font-mono text-xs text-gray-700 dark:text-gray-300 leading-relaxed">
                    <p>📋 <b>Статус вашої заявки змінено</b></p>
                    <p class="mt-1">Вакансія: <b>Senior PHP Developer</b></p>
                    <p>Компанія: <b>TechCorp</b></p>
                    <p>Новий статус: <b>Співбесіда</b></p>
                    <div class="mt-2 inline-block px-3 py-1 bg-[#2AABEE] text-white rounded-lg">Переглянути вакансію</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Unlink --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300 text-xs font-bold">5</span>
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Від'єднати Telegram</h2>
        </div>
        <div class="px-5 py-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Перейдіть у <a href="{{ route('seeker.profile') }}" class="text-blue-600 hover:underline font-medium">Мій профіль</a> → секція <strong class="font-medium text-gray-900 dark:text-white">«Telegram»</strong> → кнопка <strong class="font-medium text-gray-900 dark:text-white">«Від'єднати»</strong>.
                Підписки на категорії при цьому зберігаються — вони прив'язані до Telegram ID, а не до акаунту сайту.
            </p>
        </div>
    </div>

    {{-- Notes --}}
    <div class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800/40 rounded-2xl px-5 py-4">
        <p class="text-sm font-semibold text-amber-800 dark:text-amber-400 mb-2">⚠️ Важливо</p>
        <ul class="space-y-1.5 text-sm text-amber-700 dark:text-amber-300/80">
            <li>Сповіщення про нові вакансії надсилаються раз на годину (не в реальному часі).</li>
            <li>Підписки на категорії не потребують прив'язки до акаунту — достатньо мати Telegram ID.</li>
            <li>Якщо бот не відповідає — переконайтеся, що ви не заблокували <strong>@myjob_in_bot</strong>.</li>
        </ul>
    </div>

</div>
</div>
