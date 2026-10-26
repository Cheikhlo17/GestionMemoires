<?php

namespace App\Services\Interfaces;

use App\Models\Thesis;
use App\Models\ThesisCorrection;
use App\Models\User;
use Illuminate\Support\Collection;

interface ThesisCorrectionServiceInterface
{
    public function list(Thesis $thesis, User $viewer): Collection;

    public function create(Thesis $thesis, array $data, User $author): ThesisCorrection;

    public function updateStatus(
        ThesisCorrection $correction,
        string $status,
        User $actor,
        ?string $remarks = null
    ): ThesisCorrection;
}
