<?php

namespace App\Services\Interfaces;

use App\Models\ThesisSubject;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ThesisSubjectServiceInterface
{
    public function list(User $viewer, array $filters): LengthAwarePaginator;

    public function createProposal(array $data, User $student): ThesisSubject;

    public function updateProposal(ThesisSubject $subject, array $data, User $student): ThesisSubject;

    public function publish(array $data, User $publisher): ThesisSubject;

    public function submit(ThesisSubject $subject, User $student): ThesisSubject;

    public function select(ThesisSubject $subject, User $student): ThesisSubject;

    public function review(ThesisSubject $subject, string $status, User $reviewer, ?string $remarks = null): ThesisSubject;
}
