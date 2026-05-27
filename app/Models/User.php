<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\PlanFeature;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'telegram_id', 'city_id', 'telegram_link_token', 'provider', 'provider_id', 'profile_completeness_modal_shown_at', 'notify_via_email', 'notify_via_telegram'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
            'telegram_id'                        => 'integer',
            'profile_completeness_modal_shown_at' => 'datetime',
            'notify_via_email'    => 'boolean',
            'notify_via_telegram' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(EmployerSubscription::class)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest();
    }

    public function currentPlan(): ?SubscriptionPlan
    {
        return $this->activeSubscription?->plan;
    }

    public function hasFeature(PlanFeature $feature): bool|int
    {
        return $this->currentPlan()?->feature($feature) ?? false;
    }

    public function savedVacancies(): BelongsToMany
    {
        return $this->belongsToMany(Vacancy::class, 'saved_vacancies')
            ->withPivot('created_at')
            ->orderByPivot('created_at', 'desc');
    }

    public function candidateSkills(): BelongsToMany
    {
        return $this->belongsToMany(SkillTag::class, 'candidate_skills', 'user_id', 'skill_id')
            ->withPivot('level');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(VacancyRecommendation::class);
    }

    public function prefersEmail(): bool
    {
        return (bool) $this->notify_via_email;
    }

    public function prefersTelegram(): bool
    {
        return (bool) $this->notify_via_telegram && ! empty($this->telegram_id);
    }

    /** Phase 2: implement when CvAccess addon purchase tracking is available */
    public function hasActiveCvAccess(): bool
    {
        return false;
    }
}
