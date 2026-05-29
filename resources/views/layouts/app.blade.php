<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('seo_title', config('app.name', 'My Job'))</title>
        <meta name="description" content="@yield('seo_description', 'Оголошення про роботу в Україні — перегляд та фільтрація вакансій по всіх категоріях та містах.')">
        @yield('seo_canonical')
        @yield('seo_og')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <link rel="icon" type="image/webp" href="{{ asset('img/logo/favicon.ico') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
        <style>
            @media (max-width: 767px) {
                #main-content { padding-top: 64px !important; }
            }
        </style>
        <script>
            (function() {
                const saved = localStorage.getItem('theme') || 'light';
                document.documentElement.setAttribute('data-theme', saved);
                if (saved === 'dark') document.documentElement.classList.add('dark');
            })();
        </script>
    </head>
    <body class="font-sans antialiased bg-slate-50 text-slate-900">

        <x-header />

        <main class="min-h-screen" style="padding-top: 120px;" id="main-content">
            {{ $slot }}
        </main>

        <x-footer />

        @if(session('info'))
        <div
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 6000)"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 bg-blue-600 text-white text-sm font-medium px-5 py-3 rounded-xl shadow-lg"
        >
            <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20A10 10 0 0012 2z"/></svg>
            <span>{{ session('info') }}</span>
            <button @click="show = false" class="ml-2 opacity-70 hover:opacity-100">✕</button>
        </div>
        @endif

        <livewire:employer.quick-publish-form />
        <livewire:employer.email-setup-modal />
        @auth
            @if(auth()->user()->role === \App\Enums\UserRole::Employer)
                <livewire:employer.profile-completeness-modal />
            @endif
        @endauth

        <div
            x-data="{
                show: {{ session()->has('vacancy_published_id') ? 'true' : 'false' }},
                mode: '{{ session()->has('vacancy_published_id') ? 'published' : '' }}'
            }"
            @show-profile-required-modal.window="show = true; mode = 'profile_required'"
            x-show="show"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="display:none;"
        >
            <div class="absolute inset-0 bg-black/60" @click="show = false"></div>
            <div
                x-show="show"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-8 text-center"
                style="display:none;"
            >
                {{-- Іконка: залежить від режиму --}}
                <div x-show="mode === 'published'" class="text-5xl mb-4">🚀</div>
                <div x-show="mode === 'profile_required'"
                     class="flex items-center justify-center w-16 h-16 rounded-full bg-amber-100 mx-auto mb-4">
                    <svg class="w-8 h-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>

                {{-- Заголовок --}}
                <h2 x-show="mode === 'published'" class="text-2xl font-bold text-gray-900 mb-2">
                    Вакансія опублікована!
                </h2>
                <h2 x-show="mode === 'profile_required'" class="text-2xl font-bold text-gray-900 mb-2">
                    Вакансія не активована
                </h2>

                {{-- Підзаголовок --}}
                <p x-show="mode === 'published'" class="text-gray-500 mb-1">Ми вже почали шукати кандидатів</p>
                <p x-show="mode === 'profile_required'" class="text-gray-500 mb-1">Спочатку заповніть профіль компанії</p>

                <div class="my-6 h-px bg-gray-100"></div>

                {{-- Amber-блок (однаковий для обох режимів) --}}
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-5 text-left">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                        <div>
                            <p class="text-amber-800 font-semibold text-sm">Вакансія активна 1 добу</p>
                            <p class="text-amber-700 text-sm mt-0.5">
                                Щоб вакансія залишалась активною <strong>30 діб</strong> — заповніть профіль компанії. Це займе 1–2 хвилини.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3">
                    <a
                        href="{{ route('employer.profile') }}"
                        class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition text-center"
                    >
                        Заповнити профіль компанії
                    </a>
                    <button
                        type="button"
                        @click="show = false"
                        class="w-full py-2 text-sm text-gray-400 hover:text-gray-600 transition"
                    >
                        Пропустити
                    </button>
                </div>
            </div>
        </div>

    </body>
</html>
