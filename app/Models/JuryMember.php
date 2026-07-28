<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class JuryMember extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'department_id', 'specialization', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function defenseSchedules(): BelongsToMany
    {
        return $this->belongsToMany(DefenseSchedule::class, 'defense_jury_members')
            ->withPivot('role')
            ->withTimestamps();
    }
}