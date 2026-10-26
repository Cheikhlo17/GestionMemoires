<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreDefenseHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'pre_defense_session_id',
        'pre_defense_passage_id',
        'changed_by',
        'event',
        'old_values',
        'new_values',
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

    public function session(): BelongsTo
    {
        return $this->belongsTo(PreDefenseSession::class, 'pre_defense_session_id');
    }

    public function passage(): BelongsTo
    {
        return $this->belongsTo(PreDefensePassage::class, 'pre_defense_passage_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
