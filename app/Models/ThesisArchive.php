<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ThesisArchive extends Model
{
    use HasFactory;

    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PRIVATE = 'private';

    protected $fillable = [
        'thesis_id',
        'thesis_version_id',
        'defense_schedule_id',
        'keywords',
        'visibility',
        'show_final_grade',
        'archived_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'show_final_grade' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function finalVersion(): BelongsTo
    {
        return $this->belongsTo(ThesisVersion::class, 'thesis_version_id');
    }

    public function defenseSchedule(): BelongsTo
    {
        return $this->belongsTo(DefenseSchedule::class, 'defense_schedule_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }
}