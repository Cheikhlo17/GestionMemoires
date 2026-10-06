<?php

namespace App\Services\Interfaces;

use App\Models\Thesis;
use App\Models\ThesisArchive;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ThesisArchiveServiceInterface
{
    public function checkEligibility(Thesis $thesis): array;

    public function archive(Thesis $thesis, array $data, User $actor): ThesisArchive;

    public function update(ThesisArchive $archive, array $data, User $actor): ThesisArchive;

    public function delete(ThesisArchive $archive, User $actor): void;

    public function listPublic(array $filters): LengthAwarePaginator;

    public function listAdmin(array $filters): LengthAwarePaginator;
}