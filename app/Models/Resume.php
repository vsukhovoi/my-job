<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resume extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $fillable = [
        'user_id',
        'title',
        'status',
        'views_count',
        'personal_info',
        'location',
        'notifications',
        'additional_info',
        'last_saved_at',
        'attached_file_path',
        'attached_file_original_name',
        'attached_file_size',
        'attached_file_mime_type',
        'attached_file_uploaded_at',
    ];

    protected $casts = [
        'personal_info'           => 'array',
        'location'                => 'array',
        'notifications'           => 'array',
        'additional_info'         => 'array',
        'last_saved_at'           => 'datetime',
        'attached_file_uploaded_at' => 'datetime',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    public function hasAttachedFile(): bool
    {
        return !empty($this->attached_file_path);
    }

    public function getAttachedFileSizeFormatted(): ?string
    {
        if (!$this->attached_file_size) {
            return null;
        }

        $bytes = $this->attached_file_size;

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 2) . ' MB';
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function updatePersonalInfo(array $data): self
    {
        $this->update([
            'personal_info' => array_merge($this->personal_info ?? [], $data),
            'last_saved_at' => now(),
        ]);

        return $this->fresh();
    }

    public function updateLocation(array $data): self
    {
        $this->update([
            'location'      => array_merge($this->location ?? [], $data),
            'last_saved_at' => now(),
        ]);

        return $this->fresh();
    }

    public function updateNotifications(array $data): self
    {
        $this->update([
            'notifications' => array_merge($this->notifications ?? [], $data),
            'last_saved_at' => now(),
        ]);

        return $this->fresh();
    }

    public function updateAdditionalInfo(array $data): self
    {
        $this->update([
            'additional_info' => array_merge($this->additional_info ?? [], $data),
            'last_saved_at'   => now(),
        ]);

        return $this->fresh();
    }

    public function isPublishable(): bool
    {
        $info = $this->personal_info;

        $hasCriticalFields = !empty($info['first_name'])
            && !empty($info['last_name'])
            && $this->user_id !== null;

        $hasContent = $this->experiences()->exists() || $this->skills()->exists();

        return $hasCriticalFields && $hasContent;
    }

    public function getStepperStatus(): array
    {
        return [
            'personal_info' => $this->validatePersonalInfo(),
            'auth'          => $this->user_id !== null,
            'experience'    => $this->validateExperience(),
            'skills'        => $this->validateSkills(),
            'location'      => $this->validateLocation(),
            'notifications' => true,
        ];
    }

    private function validatePersonalInfo(): bool
    {
        $info = $this->personal_info;
        return !empty($info['first_name']) && !empty($info['last_name']);
    }

    private function validateEmail(): bool
    {
        $info = $this->personal_info;
        return !empty($info['email']) && !empty($info['email_verified_at']);
    }

    private function validateExperience(): bool
    {
        return $this->experiences()->exists();
    }

    private function validateSkills(): bool
    {
        return $this->skills()->exists();
    }

    private function validateLocation(): bool
    {
        $location = $this->location;
        return !empty($location['city']) || ($location['no_location_binding'] ?? false) === true;
    }
}
