<?php

declare(strict_types=1);

namespace Simplify\Core\Announcements\Policies;

use App\Models\User;

/** Only root admins write announcements. Everyone sees the ones meant for them as bars. */
final class AnnouncementPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isRootAdmin() ?: null;
    }

    public function viewAny(): bool
    {
        return false;
    }

    public function create(): bool
    {
        return false;
    }

    public function update(): bool
    {
        return false;
    }

    public function delete(): bool
    {
        return false;
    }
}
