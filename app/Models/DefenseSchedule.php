<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class DefenseSchedule extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_POSTPONED = 'postponed';

    protected $fillable = [
        'thesis_id',
        'defense_room_id',
        'scheduled_at',
        'duration_minutes',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(DefenseRoom::class, 'defense_room_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function juryMembers(): BelongsToMany
    {
        return $this->belongsToMany(JuryMember::class, 'defense_jury_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function result(): HasOne
    {
        return $this->hasOne(DefenseResult::class);
    }

    public function getEndsAtAttribute(): \Carbon\Carbon
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes);
    }
}