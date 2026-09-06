<?php

namespace App\Policies;

use App\Models\PersonalTrainingMember;
use App\Models\User;

class PersonalTrainingMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function view(User $user, PersonalTrainingMember $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, PersonalTrainingMember $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, PersonalTrainingMember $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, PersonalTrainingMember $record): bool
    {
        return false;
    }
}
