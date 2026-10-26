<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupervisionMeeting extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const MODE_IN_PERSON = 'in_person';
    public const MODE_ONLINE = 'online';

    protected $fillable = [
        'thesis_id',
        'created_by',
        'scheduled_at',
        'duration_minutes',
        'mode',
        'location',
        'agenda',
        'minutes',
        'difficulties',
        'decisions',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'supervision_meeting_participants')
            ->withTimestamps();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(SupervisionMeetingAction::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(SupervisionMeetingHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function getEndsAtAttribute(): \Carbon\Carbon
    {
        return $this->scheduled_at->copy()->addMinutes($this->duration_minutes);
    }
}
