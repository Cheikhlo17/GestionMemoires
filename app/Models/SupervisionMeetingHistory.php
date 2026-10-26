<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisionMeetingHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'supervision_meeting_id',
        'action_id',
        'changed_by',
        'event',
        'old_values',
        'new_values',
        'remarks',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(SupervisionMeeting::class, 'supervision_meeting_id');
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(SupervisionMeetingAction::class, 'action_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
