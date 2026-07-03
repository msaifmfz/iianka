<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReceptionDocumentType;
use App\Models\User;

class ReceptionDocumentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageContent();
    }

    public function view(User $user, ReceptionDocumentType $receptionDocumentType): bool
    {
        return $user->canManageContent();
    }

    public function create(User $user): bool
    {
        return $user->canManageContent();
    }

    public function update(User $user, ReceptionDocumentType $receptionDocumentType): bool
    {
        return $user->canManageContent();
    }
}
