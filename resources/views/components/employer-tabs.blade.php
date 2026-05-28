@php
    $currentRoute = Route::currentRouteName();

    $tabs = [
        ['route' => 'employer.dashboard',         'label' => 'Вакансії'],
        ['route' => 'employer.candidates',         'label' => 'Кандидати'],
        ['route' => 'employer.message.templates',  'label' => 'Шаблони повідомлень'],
        ['route' => 'employer.analytics',          'label' => 'Аналітика'],
        ['route' => 'employer.billing',            'label' => 'Тарифи'],
        ['route' => 'employer.payments',           'label' => 'Оплата'],
        ['route' => 'employer.profile',            'label' => 'Профіль компанії'],
        ['route' => 'employer.my-profile',         'label' => 'Мій профіль'],
        ['route' => 'employer.support',            'label' => 'Підтримка', 'badge' => true],
        ['route' => 'employer.telegram-guide',    'label' => 'Telegram-бот'],
    ];

    $activeMap = [
        'employer.vacancies.create'          => 'employer.dashboard',
        'employer.vacancies.edit'            => 'employer.dashboard',
        'employer.vacancies.payment.success' => 'employer.billing',
        'employer.billing.invoice.show'      => 'employer.payments',
        'employer.applicants'                => 'employer.dashboard',
        'employer.candidate.detail'          => 'employer.candidates',
    ];

    $activeTab = $activeMap[$currentRoute] ?? $currentRoute;

    $company = auth()->user()?->company;

    $totalVacancies    = $company ? $company->vacancies()->count() : 0;
    $activeVacancies   = $company ? $company->vacancies()->where('status', \App\Enums\VacancyStatus::Active)->count() : 0;
    $totalApplications = $company
        ? \App\Models\Application::whereHas('vacancy', fn ($q) => $q->where('company_id', $company->id))->count()
        : 0;

    $canPublish = app(\App\Services\SubscriptionService::class)->canPublishJob(auth()->user());

    $unreadSupport = \App\Models\SupportThread::where('user_id', auth()->id())
        ->whereHas('messages', fn ($q) => $q->where('is_read', false)
            ->where('sender_id', '!=', auth()->id()))
        ->count();
@endphp

{{-- ── Ліміт вакансій: modal ─────────────────────────────────────────── --}}
<div id="limit-modal-backdrop"
     style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.5);display:{{ session('limit_exceeded') ? 'flex' : 'none' }};align-items:center;justify-content:center;padding:16px;"
     onclick="if(event.target===this)closeLimitModal()">
    <div style="background:#fff;border-radius:20px;padding:36px 32px;max-width:440px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.2);position:relative;text-align:center;">

        {{-- Close --}}
        <button onclick="closeLimitModal()"
                style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:20px;color:#9ca3af;cursor:pointer;line-height:1;padding:4px 8px;border-radius:6px;"
                onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">✕</button>

        {{-- Icon --}}
        <div style="width:56px;height:56px;background:#fef3c7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:26px;">
            🔒
        </div>

        <h2 style="font-size:1.25rem;font-weight:800;color:#111827;margin:0 0 8px;">Ліміт вакансій вичерпано</h2>
        <p style="font-size:0.9rem;color:#6b7280;line-height:1.6;margin:0 0 24px;">
            Ваш поточний тариф не дозволяє публікувати більше вакансій.<br>
            Оновіть план, щоб продовжити.
        </p>

        <div style="display:flex;flex-direction:column;gap:10px;">
            <a href="{{ route('employer.billing') }}"
               style="display:block;padding:12px 20px;background:linear-gradient(135deg,#2563eb,#4f46e5);color:#fff;font-size:0.95rem;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px rgba(37,99,235,.35);">
                🚀 Оновити тариф
            </a>
            <a href="{{ route('employer.billing') }}"
               style="display:block;padding:10px 20px;background:#eff6ff;color:#2563eb;font-size:0.875rem;font-weight:600;border:1px solid #bfdbfe;border-radius:12px;text-decoration:none;">
                Переглянути всі тарифи
            </a>
            <button onclick="closeLimitModal()"
                    style="display:block;width:100%;padding:10px 20px;background:#f9fafb;color:#374151;font-size:0.875rem;font-weight:600;border:1px solid #e5e7eb;border-radius:12px;cursor:pointer;transition:background .2s;"
                    onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='#f9fafb'">
                Закрити
            </button>
        </div>
    </div>
</div>
<script>
function closeLimitModal() {
    document.getElementById('limit-modal-backdrop').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLimitModal();
});
</script>

<div class="seeker-tabs-header border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex justify-between items-center sm:grid sm:grid-cols-3 sm:gap-6">

            {{-- Col 1: Кабінет + назва + статистика --}}
            <div class="min-w-0">
                <p style="font-size:11px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.08em; margin-bottom:2px;">Кабінет роботодавця</p>
                <h1 class="employer-heading" style="font-size:1.2rem; font-weight:800; color:#111827; line-height:1.3; margin:0 0 8px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                    {{ $company?->name ?? 'Моя компанія' }}
                </h1>
                @if($company)
                <div class="flex items-center gap-3">
                    <div style="display:flex; align-items:baseline; gap:4px;">
                        <span class="text-gray-900 dark:text-white" style="font-size:1.1rem; font-weight:800;">{{ $totalVacancies }}</span>
                        <span style="font-size:0.7rem; color:#9ca3af;">всього</span>
                    </div>
                    <div style="width:1px; height:14px; background:#e5e7eb;"></div>
                    <div style="display:flex; align-items:baseline; gap:4px;">
                        <span style="font-size:1.1rem; font-weight:800; color:#16a34a;">{{ $activeVacancies }}</span>
                        <span style="font-size:0.7rem; color:#9ca3af;">активних</span>
                    </div>
                    <div style="width:1px; height:14px; background:#e5e7eb;"></div>
                    <div style="display:flex; align-items:baseline; gap:4px;">
                        <span style="font-size:1.1rem; font-weight:800; color:#2563eb;">{{ $totalApplications }}</span>
                        <span style="font-size:0.7rem; color:#9ca3af;">відгуків</span>
                    </div>
                </div>
                @endif
            </div>

            {{-- Col 2: Заповненість профілю — тільки desktop (mobile: Modal #12) --}}
            <div class="hidden sm:flex justify-center">
                @if($company)
                    <livewire:shared.profile-completeness type="employer" />
                @endif
            </div>

            {{-- Col 3: Кнопка нова вакансія --}}
            <div class="flex justify-end">
                <button
                    onclick="Livewire.dispatch('open-quick-publish')"
                    class="btn-new-vacancy"
                    style="display:inline-flex; align-items:center; gap:6px; padding:9px 18px; font-size:0.875rem; font-weight:700; color:#fff; background:#2563eb; border-radius:12px; border:none; cursor:pointer; white-space:nowrap; box-shadow:0 1px 4px rgba(37,99,235,.3);">
                    <svg style="width:16px; height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span class="hidden sm:inline">Нова вакансія</span>
                </button>
            </div>

        </div>
    </div>
</div>

{{-- Tabs --}}
<div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <nav class="-mb-px flex gap-1 overflow-x-auto scrollbar-hide dark:border-t dark:border-gray-700" aria-label="Employer tabs">
        @foreach($tabs as $tab)
            @php
                $isActive = $activeTab === $tab['route'];
            @endphp
            <a href="{{ route($tab['route']) }}"
               class="shrink-0 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors inline-flex items-center gap-1.5
                      {{ $isActive
                          ? 'border-blue-600 text-blue-600'
                          : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                {{ $tab['label'] }}
                @if(!empty($tab['badge']) && $unreadSupport > 0)
                    <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold leading-none">
                        {{ $unreadSupport }}
                    </span>
                @endif
            </a>
        @endforeach
    </nav>
    {{-- Fade indicator for horizontal scroll on mobile --}}
    <div class="pointer-events-none absolute right-0 top-0 h-full w-10 tab-scroll-fade sm:hidden"></div>
</div>
