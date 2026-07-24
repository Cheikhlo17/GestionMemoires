<?php

namespace App\Services\Interfaces;

use App\Models\Comment;
use App\Models\Thesis;
use App\Models\ThesisVersion;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface ThesisServiceInterface
{
    public function list(array $filters): LengthAwarePaginator;

    public function find(int $id): Thesis;

    public function create(array $data): Thesis;

    public function update(Thesis $thesis, array $data): Thesis;

    public function delete(Thesis $thesis): void;

    public function assignSupervisor(Thesis $thesis, int $supervisorId): Thesis;

    public function submit(Thesis $thesis): Thesis;

    public function changeStatus(Thesis $thesis, string $toStatus, User $actor, ?string $remarks = null): Thesis;

    public function uploadVersion(Thesis $thesis, UploadedFile $file, User $uploader, ?string $notes = null): ThesisVersion;

    public function addComment(Thesis $thesis, User $author, string $content, ?int $versionId = null): Comment;
}