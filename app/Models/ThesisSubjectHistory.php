<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThesisSubjectHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'thesis_subject_id',
        'from_status',
        'to_status',
        'changed_by',
        'remarks',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(ThesisSubject::class, 'thesis_subject_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
