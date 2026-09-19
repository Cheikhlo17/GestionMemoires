<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DefenseJuryEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'defense_schedule_id',
        'jury_member_id',
        'grade',
        'verdict',
        'remarks',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DefenseSchedule::class, 'defense_schedule_id');
    }

    public function juryMember(): BelongsTo
    {
        return $this->belongsTo(JuryMember::class);
    }
}