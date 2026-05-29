<?php

declare(strict_types=1);

use App\Enums\EmploymentType;
use App\Enums\Language;
use App\Enums\PlanType;
use App\Enums\Suitability;
use App\Enums\VacancyPublicationType;
use App\Enums\VacancyStatus;
use App\Models\Category;
use App\Models\Vacancy;
use App\Services\SubscriptionService;
use App\Services\VacancyService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $vacancyId = null;

    public string $title          = '';
    public string $description    = '';
    /** @var array<string> */
    public array $employmentType = [];
    public string $categoryId     = '';
    public string $cityId         = '';
    public string $cityName       = '';
    public string $salaryFrom     = '';
    public string $salaryTo       = '';
    public string $currency       = 'UAH';
    public bool   $isFeatured        = false;
    public bool   $isTop             = false;
    public bool   $saved             = false;
    public string $publicationType   = 'standard';
    public string $anonymousName     = '';

    /** @var array<string> */
    public array $languages   = [];

    /** @var array<string> */
    public array $suitability = [];

    public function mount(int $vacancyId = null): void
    {
        $this->vacancyId = $vacancyId;

        if ($vacancyId) {
            $vacancy = Vacancy::where('company_id', auth()->user()->company->id)
                ->findOrFail($vacancyId);

            $this->title          = $vacancy->title;
            $this->description    = $vacancy->description;
            $this->employmentType = $vacancy->employment_type ?? [];
            $this->categoryId     = (string) $vacancy->category_id;
            $this->cityId         = (string) ($vacancy->city_id ?? '');
            $this->cityName       = $vacancy->city?->name ?? '';
            $this->salaryFrom     = (string) ($vacancy->salary_from ?? '');
            $this->salaryTo       = (string) ($vacancy->salary_to ?? '');
            $this->currency       = $vacancy->currency;
            $this->isFeatured      = $vacancy->is_featured;
            $this->isTop           = $vacancy->is_top;
            $this->languages       = $vacancy->languages ?? [];
            $this->suitability     = $vacancy->suitability ?? [];
            $this->publicationType = $vacancy->publication_type?->value ?? 'standard';
            $this->anonymousName   = $vacancy->anonymous_name ?? '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'required|string|min:50',
            'employmentType'   => 'required|array|min:1',
            'employmentType.*' => 'in:' . implode(',', array_column(EmploymentType::cases(), 'value')),
            'categoryId'     => 'required|exists:categories,id',
            'cityId'         => 'nullable|exists:cities,id',
            'salaryFrom'     => 'nullable|integer|min:0',
            'salaryTo'       => 'nullable|integer|gte:salaryFrom',
            'currency'       => 'required|string|max:10',
            'languages'      => 'array',
            'languages.*'    => 'in:' . implode(',', array_column(Language::cases(), 'value')),
            'suitability'    => 'array',
            'suitability.*'  => 'in:' . implode(',', array_column(Suitability::cases(), 'value')),
        ]);

        $company = auth()->user()->company;

        $subscriptionService = app(SubscriptionService::class);

        if (! $this->vacancyId && ! $subscriptionService->canPublishJob(auth()->user())) {
            $this->redirect(route('employer.billing'), navigate: true);
            return;
        }

        if ($this->vacancyId) {
            $currentVacancy = Vacancy::where('company_id', $company->id)->findOrFail($this->vacancyId);

            if (! $currentVacancy->is_active && ! $company->isProfileComplete()) {
                session()->flash('info', 'Для активації вакансії необхідно спочатку заповнити профіль компанії.');
                $this->redirect(route('employer.profile'), navigate: true);
                return;
            }

            if (! $currentVacancy->is_active && ! $subscriptionService->canPublishJob(auth()->user())) {
                $this->redirect(route('employer.billing'), navigate: true);
                return;
            }
        }

        $vacancyService = app(VacancyService::class);

        if ($this->isFreePlan) {
            $this->isFeatured = false;
            $this->isTop      = false;
        }

        $data = [
            'company_id'       => $company->id,
            'category_id'      => (int) $this->categoryId,
            'city_id'          => $this->cityId ? (int) $this->cityId : null,
            'title'            => $this->title,
            'description'      => $this->description,
            'employment_type'  => $this->employmentType,
            'salary_from'      => $this->salaryFrom ?: null,
            'salary_to'        => $this->salaryTo ?: null,
            'currency'         => $this->currency,
            'is_active'        => match(true) {
                $this->publicationType !== 'anonymous'                                  => true,   // standard — завжди активна
                isset($currentVacancy) && $currentVacancy->is_active                   => true,   // редагування вже оплаченої anonymous
                default                                                                 => false,  // нова або неоплачена anonymous
            },
            'is_featured'      => $this->isFeatured,
            'is_top'           => $this->isTop,
            'status'           => VacancyStatus::Active,
            'published_at'     => now(),
            'expires_at'       => $vacancyService->getExpiresAt(auth()->user()),
            'languages'        => $this->languages ?: null,
            'suitability'      => $this->suitability ?: null,
            'publication_type' => VacancyPublicationType::from($this->publicationType),
            'anonymous_name'   => $this->publicationType === 'anonymous'
                ? ($this->anonymousName ?: null)
                : null,
        ];

        if ($this->vacancyId) {
            $vacancy = Vacancy::where('company_id', $company->id)->findOrFail($this->vacancyId);
            $vacancyService->update($vacancy, $data);
        } else {
            $vacancyService->publish(auth()->user(), $data);
        }

        if ($this->publicationType === 'anonymous') {
            session(['anonymous_vacancy_id' => $this->vacancyId ?? $company->vacancies()->latest()->first()?->id]);
            $this->redirect(route('employer.billing'), navigate: true);
            return;
        }

        $this->redirect(route('employer.dashboard'), navigate: true);
    }

    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::orderBy('position')->orderBy('name')->get();
    }

    #[Computed]
    public function employmentTypes(): array { return EmploymentType::cases(); }

    #[Computed]
    public function languageOptions(): array { return Language::cases(); }

    #[Computed]
    public function suitabilityOptions(): array { return Suitability::cases(); }

    #[Computed]
    public function isFreePlan(): bool
    {
        $plan = auth()->user()->currentPlan();
        return $plan === null || $plan->type === PlanType::Free;
    }
}; ?>

<div class="min-h-screen bg-gray-50">
    <x-employer-tabs />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="max-w-2xl mb-6">
            <a href="{{ route('employer.dashboard') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-blue-600 mb-4">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Вакансії
            </a>
            <h2 class="text-lg font-semibold text-gray-900">{{ $vacancyId ? 'Редагувати вакансію' : 'Нова вакансія' }}</h2>
        </div>

        <div class="{{ $vacancyId ? 'grid grid-cols-1 lg:grid-cols-3 gap-6 items-start' : '' }}">

        <div class="{{ $vacancyId ? 'lg:col-span-2' : 'max-w-2xl' }} bg-white rounded-2xl border border-gray-200 p-8">
            @if($saved)
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm font-medium">
                    Вакансію {{ $vacancyId ? 'оновлено' : 'опубліковано' }}.
                    <a href="{{ route('employer.dashboard') }}" class="underline ml-2">До кабінету →</a>
                </div>
            @endif

            <form wire:submit="save" class="space-y-5">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Назва посади <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="title"
                           class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"/>
                    @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3 {{ $this->isFreePlan ? 'opacity-40 cursor-not-allowed' : '' }}">
                        <input type="checkbox" wire:model="isFeatured" id="isFeatured"
                               class="w-4 h-4 rounded" style="accent-color:#e85d04;"
                               {{ $this->isFreePlan ? 'disabled' : '' }}/>
                        <label for="isFeatured" class="text-sm text-gray-700 dark:text-gray-200 {{ $this->isFreePlan ? 'cursor-not-allowed select-none' : '' }}">
                            🔥 Гаряча вакансія
                        </label>
                    </div>
                    <div class="flex items-center gap-3 {{ $this->isFreePlan ? 'opacity-40 cursor-not-allowed' : '' }}">
                        <input type="checkbox" wire:model="isTop" id="isTop"
                               class="w-4 h-4 rounded" style="accent-color:#7c3aed;"
                               {{ $this->isFreePlan ? 'disabled' : '' }}/>
                        <label for="isTop" class="text-sm text-gray-700 dark:text-gray-200 {{ $this->isFreePlan ? 'cursor-not-allowed select-none' : '' }}">
                            ⭐ Топ вакансія
                        </label>
                    </div>
                    @if($this->isFreePlan)
                        <a href="{{ route('employer.billing') }}" class="text-xs text-blue-600 hover:underline whitespace-nowrap">
                            Доступно на платних тарифах
                        </a>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Категорія <span class="text-red-500">*</span></label>
                        <select wire:model="categoryId" class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                            <option value="">Оберіть...</option>
                            @foreach($this->categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('categoryId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Тип зайнятості <span class="text-red-500">*</span></label>
                        @php
                            $etLabels = collect($this->employmentTypes)->mapWithKeys(fn($t) => [$t->value => $t->label()])->all();
                        @endphp
                        <div x-data="{
                            open: false,
                            selected: $wire.entangle('employmentType'),
                            labels: {{ json_encode($etLabels) }},
                            get display() {
                                return this.selected.length
                                    ? this.selected.map(v => this.labels[v]).join('; ')
                                    : 'Оберіть...';
                            }
                        }" @click.outside="open = false" style="position:relative;">
                            <button type="button" @click="open = !open"
                                    style="width:100%; border:1px solid var(--color-input-border); border-radius:12px; padding:8px 12px; font-size:14px; background:var(--color-input-bg); display:flex; justify-content:space-between; align-items:center; cursor:pointer; color:var(--color-input-text); text-align:left;">
                                <span x-text="display" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:90%;"></span>
                                <svg style="width:16px; height:16px; flex-shrink:0; color:#6b7280;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" x-transition
                                 style="position:absolute; z-index:50; top:calc(100% + 4px); left:0; right:0; background:var(--color-input-bg); border:1px solid var(--color-input-border); border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.1); overflow:hidden;">
                                @foreach($this->employmentTypes as $type)
                                    <label style="display:flex; align-items:center; gap:10px; padding:9px 14px; cursor:pointer; font-size:14px; color:var(--color-input-text);"
                                           onmouseover="this.style.background='var(--color-dropdown-hover)'" onmouseout="this.style.background=''">
                                        <input type="checkbox" x-model="selected" value="{{ $type->value }}"
                                               style="width:16px; height:16px; accent-color:#2563eb; cursor:pointer; flex-shrink:0;"/>
                                        {{ $type->label() }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        @error('employmentType') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Місто</label>
                    <livewire:city-search wire:model.live="cityId" :city-name="$cityName" :key="'vacancy-city-' . ($vacancyId ?? 'new')" />
                    @error('cityId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Зарплата від</label>
                        <input type="number" wire:model="salaryFrom" min="0"
                               class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Зарплата до</label>
                        <input type="number" wire:model="salaryTo" min="0"
                               class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100"/>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Валюта</label>
                        <select wire:model="currency" class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                            <option value="UAH">UAH</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Опис вакансії <span class="text-red-500">*</span></label>
                    <textarea wire:model="description" rows="8"
                              placeholder="Опишіть обов'язки, вимоги та умови роботи..."
                              class="w-full border border-gray-300 dark:border-gray-600 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500"></textarea>
                    @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                    <div>
                        <p class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Знання мов</p>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            @foreach($this->languageOptions as $lang)
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:14px; color:var(--color-input-text);">
                                    <input type="checkbox" wire:model="languages" value="{{ $lang->value }}"
                                           style="width:16px; height:16px; accent-color:#2563eb; cursor:pointer;"/>
                                    {{ $lang->label() }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <p class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Підходить</p>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            @foreach($this->suitabilityOptions as $item)
                                <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:14px; color:var(--color-input-text);">
                                    <input type="checkbox" wire:model="suitability" value="{{ $item->value }}"
                                           style="width:16px; height:16px; accent-color:#2563eb; cursor:pointer;"/>
                                    {{ $item->label() }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="rounded-xl p-4" style="border: 1px solid #E5E7EB; background: #F9FAFB;">
                    <h3 class="font-semibold mb-3" style="color: #1F2937;">Тип публікації</h3>

                    <div class="space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="radio" wire:model.live="publicationType" value="standard" class="mt-1" />
                            <div>
                                <p class="font-medium" style="color: #1F2937;">Звичайна — безкоштовно</p>
                                <p class="text-sm" style="color: #6B7280;">
                                    Назва та профіль компанії відображаються для кандидатів
                                </p>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="radio" wire:model.live="publicationType" value="anonymous" class="mt-1" />
                            <div>
                                <p class="font-medium" style="color: #1F2937;">Анонімна — платна послуга</p>
                                <p class="text-sm" style="color: #6B7280;">
                                    Бренд прихований. Автооновлення позиції щотижня.
                                    Вакансія відсутня у списку вакансій компанії.
                                </p>
                            </div>
                        </label>
                    </div>

                    @if($publicationType === 'anonymous')
                        <div class="mt-4">
                            <label class="block text-sm font-medium mb-1" style="color: #374151;">
                                Назва для відображення
                            </label>
                            <input type="text"
                                   wire:model="anonymousName"
                                   placeholder="Компанія"
                                   maxlength="100"
                                   class="w-full px-3 py-2 text-sm"
                                   style="border: 1px solid var(--color-input-border);
                                          border-radius: 12px;
                                          background: var(--color-input-bg);
                                          color: var(--color-input-text);" />
                            <p class="mt-1 text-xs" style="color: #6B7280;">
                                Залиш порожнім — буде відображатись «Компанія»
                            </p>
                        </div>

                        <div class="mt-4 p-3 rounded-lg text-sm"
                             style="background: #FFFBEB; border: 1px solid #FCD34D; color: #92400E;">
                            ⚠ Анонімна публікація — платна послуга.
                            Після збереження вас буде направлено до оплати.
                        </div>
                    @endif
                </div>

                <button type="submit"
                        wire:loading.attr="disabled"
                        style="width:100%; background:#2563eb; color:#fff; font-weight:700; font-size:0.9rem; padding:10px 16px; border:none; border-radius:12px; cursor:pointer; margin-top:8px; display:block;">
                    <span wire:loading.remove wire:target="save">Зберегти вакансію</span>
                    <span wire:loading wire:target="save">Збереження...</span>
                </button>

            </form>
        </div>

        @if($vacancyId)
            <aside class="lg:col-span-1 sticky top-4 space-y-4">
                <livewire:shared.profile-completeness
                    type="vacancy"
                    :model-id="$vacancyId"
                    :wire:key="'completeness-' . $vacancyId"
                />
                <livewire:employer.vacancy-countdown
                    :vacancy-id="$vacancyId"
                    :wire:key="'countdown-' . $vacancyId"
                />
            </aside>
        @endif

        </div>{{-- end grid --}}
    </div>
</div>
