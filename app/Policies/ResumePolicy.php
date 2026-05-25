<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Resume;
use App\Models\User;

class ResumePolicy
{
    public function view(User $user, Resume $resume): bool
    {
        return $user->id === $resume->user_id;
    }

    public function update(User $user, Resume $resume): bool
    {
        return $user->id === $resume->user_id;
    }

    public function delete(User $user, Resume $resume): bool
    {
        return $user->id === $resume->user_id;
    }

    public function downloadFile(User $user, Resume $resume): bool
    {
        if ($user->id === $resume->user_id) {
            return true;
        }

        if ($user->role === UserRole::Employer) {
            return $user->hasActiveCvAccess();
        }

        return false;
    }
}
