<?php

namespace App\Services\Interfaces;

use App\Models\PreDefensePassage;
use App\Models\PreDefenseSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PreDefenseServiceInterface
{
    public function list(User $viewer, array $filters): LengthAwarePaginator;

    public function createSession(array $data, User $actor): PreDefenseSession;

    public function updateSession(PreDefenseSession $session, array $data, User $actor): PreDefenseSession;

    public function addPassage(PreDefenseSession $session, array $data, User $actor): PreDefensePassage;

    public function updatePassage(
        PreDefenseSession $session,
        PreDefensePassage $passage,
        array $data,
        User $actor
    ): PreDefensePassage;

    public function updateSessionStatus(PreDefenseSession $session, string $status, User $actor): PreDefenseSession;
}
