<x-app-layout>

@section('seo_title', 'Контакти та підтримка — My Job')
@section('seo_description', "Зв'яжіться з командою My Job. Підтримка для кандидатів та роботодавців, партнерство, технічні питання.")
@section('seo_canonical')
<link rel="canonical" href="{{ url('/contacts') }}">
@endsection
@section('seo_og')
<meta property="og:type" content="website">
<meta property="og:title" content="Контакти та підтримка — My Job">
<meta property="og:description" content="Ми відповідаємо на всі звернення протягом 1 робочого дня.">
<meta property="og:url" content="{{ url('/contacts') }}">
<meta property="og:image" content="{{ asset('img/logo/mj-logo-1300x1300.webp') }}">
<meta property="og:locale" content="uk_UA">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Контакти та підтримка — My Job">
<meta name="twitter:description" content="Ми відповідаємо на всі звернення протягом 1 робочого дня.">
<meta name="twitter:image" content="{{ asset('img/logo/mj-logo-1300x1300.webp') }}">
@endsection

<div class="max-w-5xl mx-auto px-4 py-12">

    {{-- Header --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-end mb-10">
        <div>
            <h1 class="text-4xl font-medium leading-tight tracking-tight mb-3">
                Контакти<br><span class="text-green-600">та підтримка</span>
            </h1>
            <p class="text-gray-500 text-sm leading-relaxed">
                Ми відповідаємо на всі звернення протягом 1 робочого дня.
                Напишіть нам — допоможемо.
            </p>
        </div>
        <div class="flex flex-col items-start md:items-end gap-2 text-sm text-gray-500">
            <span class="flex items-center gap-2 border border-gray-100 rounded-full px-4 py-2 bg-white">
                <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                support@myjob.co.ua
                <span class="text-xs text-gray-400">підтримка</span>
            </span>
            <span class="flex items-center gap-2 border border-gray-100 rounded-full px-4 py-2 bg-white">
                <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                sales@myjob.co.ua
                <span class="text-xs text-gray-400">роботодавцям</span>
            </span>
            <span class="flex items-center gap-2 border border-gray-100 rounded-full px-4 py-2 bg-white">
                <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                partnership@myjob.co.ua
                <span class="text-xs text-gray-400">партнерство</span>
            </span>
            <span class="flex items-center gap-2 border border-gray-100 rounded-full px-4 py-2 bg-white">
                <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Пн–Пт, 09:00–18:00
            </span>
        </div>
    </div>

    <hr class="border-gray-100 mb-10" />

    <div class="grid grid-cols-1 md:grid-cols-[1fr_1.4fr] gap-12 items-start">

        {{-- Sidebar --}}
        <div>
            <h2 class="text-xs font-medium uppercase tracking-widest text-gray-400 mb-6">
                Із чим допомагаємо
            </h2>
            <div class="space-y-6 text-sm">
                <div>
                    <p class="text-xs text-gray-400 mb-1">Для кандидатів</p>
                    <p class="text-gray-700 leading-relaxed">
                        Перегляд та фільтрація оголошень, редагування резюме, налаштування акаунту —
                        <a href="mailto:support@myjob.co.ua" class="text-green-600">support@myjob.co.ua</a>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-1">Для роботодавців</p>
                    <p class="text-gray-700 leading-relaxed">
                        Публікація вакансій, тарифи, доступ до бази CV —
                        <a href="mailto:sales@myjob.co.ua" class="text-green-600">sales@myjob.co.ua</a>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-1">Технічні питання</p>
                    <p class="text-gray-700 leading-relaxed">
                        Помилки на платформі, інтеграція з Telegram, оплата —
                        <a href="mailto:support@myjob.co.ua" class="text-green-600">support@myjob.co.ua</a>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-1">Партнерство</p>
                    <p class="text-gray-700 leading-relaxed">
                        Корпоративні рішення, API-доступ, медіаспівпраця —
                        <a href="mailto:partnership@myjob.co.ua" class="text-green-600">partnership@myjob.co.ua</a>
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 mt-6">
                <span class="text-xs border border-gray-200 rounded-md px-3 py-1 text-gray-500">Безкоштовна підтримка</span>
                <span class="text-xs border border-gray-200 rounded-md px-3 py-1 text-gray-500">Українська мова</span>
                <span class="text-xs border border-gray-200 rounded-md px-3 py-1 text-gray-500">Конфіденційно</span>
            </div>
        </div>

        {{-- Form card --}}
        <div class="bg-white border border-gray-100 rounded-2xl p-7 shadow-sm">
            <h2 class="text-base font-medium mb-5">Написати нам</h2>
            <livewire:contacts.contact-form />
        </div>

    </div>
</div>

</x-app-layout>
