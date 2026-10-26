<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreDefensePassage extends Model
{
    use HasFactory;

    public const OUTCOME_PENDING = 'pending';
    public const OUTCOME_READY = 'ready';
    public const OUTCOME_NEEDS_REVISION = 'needs_revision';

    protected $fillable = [
        'pre_defense_session_id',
        'thesis_id',
        'student_id',
        'position',
        'scheduled_at',
        'duration_minutes',
        'observations',
        'recommendations',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PreDefenseSession::class, 'pre_defense_session_id');
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getEndsAtAttribute(): \Carbon\Carbon
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes);
    }
}
