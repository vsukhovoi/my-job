<x-app-layout>

@push('head')
<meta name="description" content="My Job — сучасна українська платформа пошуку роботи. Дізнайтесь про нашу місію, цінності та команду.">
<meta property="og:title" content="Про нас — My Job">
<meta property="og:description" content="Ми будуємо майбутнє ринку праці України. Чесно, швидко та без зайвих складнощів.">
<meta property="og:url" content="{{ url('/about') }}">
@endpush

{{-- HERO --}}
<section class="py-20 px-6 lg:px-16 bg-white">
    <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

        <div>
            <span class="inline-block bg-blue-50 text-blue-700 text-xs font-semibold uppercase tracking-widest px-4 py-1.5 rounded-full mb-6">
                🇺🇦 Новий український сервіс
            </span>
            <h1 class="text-4xl lg:text-5xl font-extrabold text-gray-900 leading-tight mb-6">
                Ми будуємо<br>
                <span class="text-blue-600">майбутнє</span> ринку<br>
                праці України
            </h1>
            <p class="text-gray-500 text-lg leading-relaxed mb-8">
                My Job — сучасна платформа, яка з'єднує талановитих кандидатів із гідними роботодавцями. Чесно, швидко та без зайвих складнощів.
            </p>
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('home') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-xl transition">
                    Знайти вакансію
                </a>
                <a href="{{ route('employer.vacancies.create') }}"
                   class="border-2 border-blue-600 text-blue-600 hover:bg-blue-50 font-semibold px-6 py-3 rounded-xl transition">
                    Розмістити вакансію
                </a>
            </div>
        </div>

        <div class="about-info-block rounded-2xl p-8 flex flex-col gap-4 border">
            <div class="bg-white rounded-xl px-5 py-4 flex items-center gap-4 border border-gray-100 shadow-sm">
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-xl flex-shrink-0">💻</div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-900 text-sm truncate">Frontend Developer</p>
                    <p class="text-xs text-gray-500 truncate">IT-компанія · Дніпро / Remote</p>
                </div>
                <span class="bg-blue-50 text-blue-700 text-xs font-semibold px-2.5 py-1 rounded-lg whitespace-nowrap">Нова</span>
            </div>
            <div class="bg-white rounded-xl px-5 py-4 flex items-center gap-4 border border-gray-100 shadow-sm">
                <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center text-xl flex-shrink-0">📊</div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-900 text-sm truncate">Менеджер з продажів</p>
                    <p class="text-xs text-gray-500 truncate">Торгова мережа · Київ</p>
                </div>
                <span class="bg-green-50 text-green-700 text-xs font-semibold px-2.5 py-1 rounded-lg whitespace-nowrap">Гаряча</span>
            </div>
            <div class="bg-white rounded-xl px-5 py-4 flex items-center gap-4 border border-gray-100 shadow-sm">
                <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center text-xl flex-shrink-0">🎨</div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-900 text-sm truncate">UX/UI Designer</p>
                    <p class="text-xs text-gray-500 truncate">Стартап · Повна зайнятість</p>
                </div>
                <span class="bg-orange-50 text-orange-700 text-xs font-semibold px-2.5 py-1 rounded-lg whitespace-nowrap">Топ</span>
            </div>
            <p class="text-center text-sm text-blue-600 font-semibold pt-1">+ сотні нових вакансій щодня</p>
        </div>

    </div>
</section>

{{-- МІСІЯ --}}
<section class="py-20 px-6 lg:px-16 bg-gray-50 border-t border-gray-100">
    <div class="max-w-6xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

        <div>
            <p class="text-xs font-semibold text-blue-600 uppercase tracking-widest mb-3">Наша місія</p>
            <h2 class="text-3xl font-extrabold text-gray-900 leading-snug mb-5">
                Перегляд оголошень — без стресу
            </h2>
            <p class="text-gray-500 text-base leading-relaxed mb-8">
                Ми створюємо сервіс, де кожен українець може переглянути оголошення про роботу, що відповідають його можливостям, цінностям та амбіціям. Без зайвих посередників, прихованих умов і застарілих підходів.
            </p>
            <ul class="space-y-4">
                <li class="flex items-start gap-3">
                    <span class="mt-1.5 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                    <span class="text-gray-600 text-sm leading-relaxed">
                        <strong class="text-gray-900">Чесність і прозорість</strong> — лише реальні вакансії від перевірених роботодавців
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-1.5 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                    <span class="text-gray-600 text-sm leading-relaxed">
                        <strong class="text-gray-900">Турбота про кандидата</strong> — захист від шахрайства та недобросовісних практик
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="mt-1.5 w-2 h-2 rounded-full bg-blue-600 flex-shrink-0"></span>
                    <span class="text-gray-600 text-sm leading-relaxed">
                        <strong class="text-gray-900">Постійний розвиток</strong> — сучасні технології для кращого результату
                    </span>
                </li>
            </ul>
        </div>

        <div class="bg-white rounded-2xl p-8 border border-gray-200 shadow-sm">
            <span class="block text-7xl font-extrabold text-blue-200 leading-none mb-0">"</span>
            <p class="text-gray-700 text-lg leading-relaxed italic mb-6 -mt-4">
                Кожна людина заслуговує на роботу, яка приносить не лише зарплату, а й задоволення та сенс. Саме тому ми і створили My Job.
            </p>
            <p class="text-gray-400 text-sm">— Команда My Job</p>
        </div>

    </div>
</section>

{{-- ПЕРЕВАГИ --}}
<section class="py-20 px-6 lg:px-16 bg-white border-t border-gray-100">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-xs font-semibold text-blue-600 uppercase tracking-widest mb-3">Переваги</p>
            <h2 class="text-3xl font-extrabold text-gray-900">Чому обирають My Job</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

            <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-900 mb-2">Швидкість</h3>
                <p class="text-sm text-gray-500 leading-relaxed">Публікація вакансії за 2 хвилини. Відгук на вакансію в один клік.</p>
            </div>

            <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-900 mb-2">Точний підбір</h3>
                <p class="text-sm text-gray-500 leading-relaxed">Розумна система підбору кандидатів і вакансій за навичками та досвідом.</p>
            </div>

            <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-900 mb-2">Захист і довіра</h3>
                <p class="text-sm text-gray-500 leading-relaxed">Верифіковані роботодавці, ЄДРПОУ-перевірка, захист від шахраїв.</p>
            </div>

            <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100">
                <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center mb-4">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-900 mb-2">Зручний інтерфейс</h3>
                <p class="text-sm text-gray-500 leading-relaxed">Сучасний дизайн, Telegram-бот, сповіщення та мобільна версія.</p>
            </div>

        </div>
    </div>
</section>

{{-- СТАТИСТИКА --}}
<section class="py-20 px-6 lg:px-16 bg-blue-600">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-xs font-semibold text-blue-200 uppercase tracking-widest mb-3">My Job у цифрах</p>
            <h2 class="text-3xl font-extrabold text-white">Зростаємо разом з вами</h2>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">

            <div class="bg-white/10 backdrop-blur rounded-2xl p-7 text-center border border-white/20">
                <p class="text-3xl font-extrabold text-white mb-1">500+</p>
                <p class="text-sm text-blue-100">Вакансій на старті</p>
                <p class="text-xs text-blue-300 mt-1">і ростемо щодня</p>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-2xl p-7 text-center border border-white/20">
                <p class="text-3xl font-extrabold text-white mb-1">100%</p>
                <p class="text-sm text-blue-100">Власна розробка</p>
                <p class="text-xs text-blue-300 mt-1">Laravel · Livewire · MySQL</p>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-2xl p-7 text-center border border-white/20">
                <p class="text-3xl font-extrabold text-white mb-1">24/7</p>
                <p class="text-sm text-blue-100">Доступність платформи</p>
                <p class="text-xs text-blue-300 mt-1">без перебоїв</p>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-2xl p-7 text-center border border-white/20">
                <p class="text-3xl font-extrabold text-white mb-1">2 хв</p>
                <p class="text-sm text-blue-100">Публікація вакансії</p>
                <p class="text-xs text-blue-300 mt-1">від реєстрації до публікації</p>
            </div>

        </div>
    </div>
</section>

{{-- ДЛЯ КОГО --}}
<section class="py-20 px-6 lg:px-16 bg-white border-t border-gray-100">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-xs font-semibold text-blue-600 uppercase tracking-widest mb-3">Для кого</p>
            <h2 class="text-3xl font-extrabold text-gray-900">My Job — для кожного</h2>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <div class="about-feature-card border rounded-2xl p-8">
                <h3 class="text-xl font-extrabold text-blue-700 mb-3">Для кандидатів</h3>
                <p class="about-card-desc text-sm leading-relaxed mb-6">
                    Знаходьте роботу, яка вам підходить. Будуйте резюме, відгукуйтесь в один клік, отримуйте запрошення від роботодавців.
                </p>
                <ul class="space-y-3">
                    <li class="about-card-item flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Пошук по всій Україні та remote
                    </li>
                    <li class="about-card-item flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Конструктор резюме
                    </li>
                    <li class="about-card-item flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        Сповіщення про нові вакансії
                    </li>
                    <li class="about-card-item flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        Асинхронні інтерв'ю з роботодавцями
                    </li>
                    <li class="about-card-item flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        Telegram-бот для зручності
                    </li>
                </ul>
            </div>

            <div class="about-feature-card border rounded-2xl p-8">
                <h3 class="text-xl font-extrabold text-green-700 mb-3">Для роботодавців</h3>
                <p class="about-card-desc text-sm leading-relaxed mb-6">
                    Швидко знаходьте потрібних спеціалістів. Управляйте вакансіями та кандидатами з єдиного кабінету.
                </p>
                <ul class="space-y-3">
                    <li class="about-card-item flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Публікація вакансій за 2 хвилини
                    </li>
                    <li class="about-card-item flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        База верифікованих кандидатів
                    </li>
                    <li class="about-card-item flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Аналітика та статистика
                    </li>
                    <li class="about-card-item flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        Анонімні вакансії
                    </li>
                    <li class="about-card-item flex items-center gap-3 text-sm">
                        <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Бренд роботодавця
                    </li>
                </ul>
            </div>

        </div>
    </div>
</section>

{{-- ТАЙМЛАЙН --}}
<section class="py-20 px-6 lg:px-16 bg-gray-50 border-t border-gray-100">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-14">
            <p class="text-xs font-semibold text-blue-600 uppercase tracking-widest mb-3">Наша історія</p>
            <h2 class="text-3xl font-extrabold text-gray-900">Від ідеї до запуску</h2>
        </div>

        <div class="relative">
            <div class="hidden lg:block absolute top-5 left-[10%] right-[10%] h-px bg-gray-200"></div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">

                <div class="flex lg:flex-col items-center lg:items-center gap-4 lg:gap-0 lg:text-center">
                    <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0 lg:mb-3 z-10">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-gray-900 text-sm">2024</p>
                        <p class="text-xs text-gray-500 mt-1 max-w-[120px]">Народження ідеї та дослідження ринку</p>
                    </div>
                </div>

                <div class="flex lg:flex-col items-center lg:items-center gap-4 lg:gap-0 lg:text-center">
                    <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0 lg:mb-3 z-10">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-gray-900 text-sm">Поч. 2025</p>
                        <p class="text-xs text-gray-500 mt-1 max-w-[120px]">Початок розробки платформи</p>
                    </div>
                </div>

                <div class="flex lg:flex-col items-center lg:items-center gap-4 lg:gap-0 lg:text-center">
                    <div class="w-10 h-10 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0 lg:mb-3 z-10">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-gray-900 text-sm">Трав. 2026</p>
                        <p class="text-xs text-gray-500 mt-1 max-w-[120px]">Завершення розробки, бета-тестування</p>
                    </div>
                </div>

                <div class="flex lg:flex-col items-center lg:items-center gap-4 lg:gap-0 lg:text-center">
                    <div class="w-10 h-10 rounded-full bg-amber-500 flex items-center justify-center flex-shrink-0 lg:mb-3 z-10">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-amber-600 text-sm">Серп. 2026</p>
                        <p class="text-xs text-gray-500 mt-1 max-w-[120px]">Офіційний запуск myjob.co.ua</p>
                    </div>
                </div>

                <div class="flex lg:flex-col items-center lg:items-center gap-4 lg:gap-0 lg:text-center">
                    <div class="w-10 h-10 rounded-full bg-white border border-gray-200 flex items-center justify-center flex-shrink-0 lg:mb-3 z-10">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="font-bold text-gray-400 text-sm">2027+</p>
                        <p class="text-xs text-gray-400 mt-1 max-w-[120px]">Масштабування по всій Україні</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

{{-- КОМАНДА --}}
{{-- <section class="py-20 px-6 lg:px-16 bg-white border-t border-gray-100">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-xs font-semibold text-blue-600 uppercase tracking-widest mb-3">Команда</p>
            <h2 class="text-3xl font-extrabold text-gray-900">Хто стоїть за My Job</h2>
            <p class="text-gray-500 text-sm mt-3 max-w-md mx-auto leading-relaxed">
                Ми — підприємці, які добре знають проблеми українського ринку праці і хочуть зробити його кращим.
            </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">

            <div class="bg-gray-50 rounded-2xl p-7 text-center border border-gray-100">
                <div class="w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center text-blue-700 font-extrabold text-lg mx-auto mb-4">ЗА</div>
                <p class="font-semibold text-gray-900 mb-1">Засновник</p>
                <p class="text-xs text-gray-500 mb-3">Продукт & Розробка</p>
                <p class="text-xs text-gray-400 leading-relaxed">Повний цикл розробки: від архітектури до фронтенду та бізнес-логіки.</p>
            </div>

            <div class="bg-gray-50 rounded-2xl p-7 text-center border border-gray-100">
                <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center text-green-700 font-extrabold text-lg mx-auto mb-4">БП</div>
                <p class="font-semibold text-gray-900 mb-1">Бізнес-розвиток</p>
                <p class="text-xs text-gray-500 mb-3">Партнерства & Продажі</p>
                <p class="text-xs text-gray-400 leading-relaxed">Залучення роботодавців та побудова партнерської мережі по Україні.</p>
            </div>

            <div class="bg-gray-50 rounded-2xl p-7 text-center border border-gray-100">
                <div class="w-14 h-14 rounded-full bg-orange-50 flex items-center justify-center text-orange-700 font-extrabold text-lg mx-auto mb-4">МК</div>
                <p class="font-semibold text-gray-900 mb-1">Маркетинг</p>
                <p class="text-xs text-gray-500 mb-3">Зростання & Комʼюніті</p>
                <p class="text-xs text-gray-400 leading-relaxed">Залучення кандидатів, контент та розвиток спільноти My Job.</p>
            </div>

        </div>
    </div>
</section> --}}

{{-- CTA --}}
<section class="py-24 px-6 lg:px-16 bg-gray-50 border-t border-gray-100">
    <div class="max-w-2xl mx-auto text-center">
        <p class="text-xs font-semibold text-blue-600 uppercase tracking-widest mb-4">Готові?</p>
        <h2 class="text-3xl font-extrabold text-gray-900 mb-4">Приєднуйтесь до My Job</h2>
        <p class="text-gray-500 text-base leading-relaxed mb-10">
            Будьте серед перших, хто скористається новим сервісом пошуку роботи в Україні.
        </p>
        <div class="flex flex-wrap gap-4 justify-center">
            <a href="{{ route('home') }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-8 py-4 rounded-xl transition text-base">
                Знайти вакансію
            </a>
            <a href="{{ route('employer.vacancies.create') }}"
               class="border-2 border-blue-600 text-blue-600 hover:bg-blue-50 font-semibold px-8 py-4 rounded-xl transition text-base">
                Розмістити вакансію
            </a>
        </div>
        <p class="text-xs text-gray-400 mt-8">myjob.co.ua · Запуск: серпень 2026</p>
    </div>
</section>

</x-app-layout>
