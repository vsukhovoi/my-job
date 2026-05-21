@php
    $currentRoute = Route::currentRouteName();

    $tabs = [
        ['route' => 'seeker.dashboard',    'label' => 'Огляд'],
        ['route' => 'seeker.resumes',      'label' => 'Мої резюме'],
        ['route' => 'seeker.applications', 'label' => 'Мої заявки'],
        ['route' => 'seeker.interviews',   'label' => 'Співбесіди'],
        ['route' => 'seeker.offers',       'label' => 'Пропозиції'],
        ['route' => 'seeker.saved',        'label' => 'Збережені'],
        ['route' => 'seeker.recommended',  'label' => 'Рекомендовані'],
        ['route' => 'seeker.messages',     'label' => 'Повідомлення', 'badge' => true],
        ['route' => 'seeker.profile',      'label' => 'Мій профіль'],
    ];

    $activeMap = [
        'seeker.application.detail' => 'seeker.applications',
        'seeker.message.detail'     => 'seeker.messages',
    ];

    $activeTab = $activeMap[$currentRoute] ?? $currentRoute;

    $user            = auth()->user();
    $unreadMessages  = \App\Models\SupportThread::where('user_id', $user->id)
                        ->whereHas('messages', fn($q) => $q->where('is_read', false)
                            ->where('sender_id', '!=', $user->id))
                        ->count();
    $totalApps       = $user->applications()->count();
    $activeApps      = $user->applications()->whereIn('status', ['pending', 'screening', 'interview'])->count();
    $upcomingIntvs   = \App\Models\Interview::whereHas('application', fn($q) => $q->where('user_id', $user->id))
                        ->where('scheduled_at', '>=', now())
                        ->whereIn('status', [\App\Enums\InterviewStatus::Scheduled->value, \App\Enums\InterviewStatus::Confirmed->value])
                        ->count();
@endphp

<div class="seeker-tabs-header border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Header row --}}
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 pt-5 pb-3">

            {{-- Left: user name --}}
            <div class="shrink-0">
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wide mb-0.5">Кабінет кандидата</p>
                <h1 class="seeker-header-title text-xl font-extrabold text-gray-900 leading-tight m-0">
                    {{ $user->name }}
                </h1>
            </div>

            {{-- Center: stats (hidden on xs, visible from sm) --}}
            <div class="hidden sm:flex items-center gap-5">
                <div class="flex items-baseline gap-1.5">
                    <span class="seeker-header-title text-2xl font-extrabold text-gray-900">{{ $totalApps }}</span>
                    <span class="text-xs text-gray-400 whitespace-nowrap">всього заявок</span>
                </div>
                <div class="w-px h-5 bg-gray-200"></div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-extrabold text-blue-600">{{ $activeApps }}</span>
                    <span class="text-xs text-gray-400 whitespace-nowrap">активних</span>
                </div>
                <div class="w-px h-5 bg-gray-200"></div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-extrabold text-violet-600">{{ $upcomingIntvs }}</span>
                    <span class="text-xs text-gray-400 whitespace-nowrap">співбесід</span>
                </div>
            </div>

            {{-- Right: CTA --}}
            <a href="{{ route('home') }}"
               class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 text-sm font-bold text-white bg-blue-600 rounded-xl whitespace-nowrap shadow-sm hover:bg-blue-700 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Знайти вакансії
            </a>

            {{-- Stats row on mobile only --}}
            <div class="flex sm:hidden w-full items-center gap-4 pb-1">
                <div class="flex items-baseline gap-1">
                    <span class="text-lg font-extrabold text-gray-900">{{ $totalApps }}</span>
                    <span class="text-xs text-gray-400">заявок</span>
                </div>
                <div class="w-px h-4 bg-gray-200"></div>
                <div class="flex items-baseline gap-1">
                    <span class="text-lg font-extrabold text-blue-600">{{ $activeApps }}</span>
                    <span class="text-xs text-gray-400">активних</span>
                </div>
                <div class="w-px h-4 bg-gray-200"></div>
                <div class="flex items-baseline gap-1">
                    <span class="text-lg font-extrabold text-violet-600">{{ $upcomingIntvs }}</span>
                    <span class="text-xs text-gray-400">співбесід</span>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <nav class="-mb-px flex gap-1 overflow-x-auto dark:border-t dark:border-gray-700" aria-label="Seeker tabs">
            @foreach($tabs as $tab)
                @php $isActive = $activeTab === $tab['route']; @endphp
                <a href="{{ route($tab['route']) }}"
                   class="shrink-0 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors inline-flex items-center gap-1.5
                          {{ $isActive
                              ? 'border-blue-600 text-blue-600'
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    {{ $tab['label'] }}
                    @if(!empty($tab['badge']) && $unreadMessages > 0)
                        <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                            {{ $unreadMessages }}
                        </span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>
</div>
