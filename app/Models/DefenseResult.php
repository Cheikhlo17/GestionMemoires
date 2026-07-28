<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DefenseResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'defense_schedule_id',
        'final_grade',
        'verdict',
        'remarks',
        'recorded_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'final_grade' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DefenseSchedule::class, 'defense_schedule_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}