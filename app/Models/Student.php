<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = ['nis', 'name', 'gender', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function hasScores(): bool
    {
        return Score::query()
            ->whereHas('enrollment', fn ($q) => $q->where('student_id', $this->id))
            ->whereAnyNotNull()
            ->exists();
    }
}
