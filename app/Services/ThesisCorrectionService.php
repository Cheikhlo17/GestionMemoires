<?php

namespace App\Services;

use App\Models\Thesis;
use App\Models\ThesisCorrection;
use App\Models\ThesisCorrectionHistory;
use App\Models\User;
use App\Services\Interfaces\ThesisCorrectionServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ThesisCorrectionService implements ThesisCorrectionServiceInterface
{
    public function list(Thesis $thesis, User $viewer): Collection
    {
        Gate::forUser($viewer)->authorize('view', $thesis);

        return $thesis->corrections()
            ->with(['version', 'author', 'resolver', 'history.changedBy'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(Thesis $thesis, array $data, User $author): ThesisCorrection
    {
        Gate::forUser($author)->authorize('createCorrection', $thesis);

        return DB::transaction(function () use ($thesis, $data, $author) {
            $version = $thesis->versions()
                ->lockForUpdate()
                ->whereKey($data['thesis_version_id'])
                ->first();

            if (!$version) {
                throw ValidationException::withMessages([
                    'thesis_version_id' => ['The selected version must belong to this thesis.'],
                ]);
            }

            $correction = ThesisCorrection::create([
                ...$data,
                'thesis_id' => $thesis->id,
                'author_id' => $author->id,
                'status' => ThesisCorrection::STATUS_TO_CORRECT,
            ]);
            $this->recordHistory($correction, null, ThesisCorrection::STATUS_TO_CORRECT, $author, $data['remark']);

            return $correction->load(['version', 'author', 'history.changedBy']);
        });
    }

    public function updateStatus(
        ThesisCorrection $correction,
        string $status,
        User $actor,
        ?string $remarks = null
    ): ThesisCorrection {
        Gate::forUser($actor)->authorize('updateStatus', [$correction, $status]);

        return DB::transaction(function () use ($correction, $status, $actor, $remarks) {
            $correction = ThesisCorrection::query()->lockForUpdate()->findOrFail($correction->id);
            Gate::forUser($actor)->authorize('updateStatus', [$correction, $status]);

            $allowed = match ($status) {
                ThesisCorrection::STATUS_CORRECTED => $correction->status === ThesisCorrection::STATUS_TO_CORRECT,
                ThesisCorrection::STATUS_VALIDATED,
                ThesisCorrection::STATUS_TO_CORRECT => $correction->status === ThesisCorrection::STATUS_CORRECTED,
                default => false,
            };

            if (!$allowed) {
                throw ValidationException::withMessages([
                    'status' => ['This correction cannot transition from its current status to the requested status.'],
                ]);
            }

            $previousStatus = $correction->status;
            $correction->status = $status;
            $correction->resolved_by = in_array($status, [
                ThesisCorrection::STATUS_VALIDATED,
                ThesisCorrection::STATUS_TO_CORRECT,
            ], true) ? $actor->id : null;
            $correction->resolved_at = $correction->resolved_by ? now() : null;
            $correction->save();
            $this->recordHistory($correction, $previousStatus, $status, $actor, $remarks);

            return $correction->fresh(['version', 'author', 'resolver', 'history.changedBy']);
        });
    }

    private function recordHistory(
        ThesisCorrection $correction,
        ?string $fromStatus,
        string $toStatus,
        User $actor,
        ?string $remarks
    ): void {
        ThesisCorrectionHistory::create([
            'thesis_correction_id' => $correction->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $actor->id,
            'remarks' => $remarks,
        ]);
    }
}
