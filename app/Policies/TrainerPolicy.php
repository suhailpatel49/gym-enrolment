<?php

namespace App\Policies;

use App\Models\Trainer;
use App\Models\User;

class TrainerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function view(User $user, Trainer $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Trainer $record): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Trainer $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Trainer $record): bool
    {
        return false;
    }
}
