<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThesisCorrection extends Model
{
    use HasFactory;

    public const STATUS_TO_CORRECT = 'to_correct';
    public const STATUS_CORRECTED = 'corrected';
    public const STATUS_VALIDATED = 'validated';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    protected $fillable = [
        'thesis_id',
        'thesis_version_id',
        'author_id',
        'resolved_by',
        'page_number',
        'section',
        'anchor',
        'remark',
        'priority',
        'status',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ThesisVersion::class, 'thesis_version_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ThesisCorrectionHistory::class)->orderBy('created_at')->orderBy('id');
    }
}
