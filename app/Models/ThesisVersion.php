<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ThesisVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'thesis_id',
        'version_number',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by',
        'notes',
    ];

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}