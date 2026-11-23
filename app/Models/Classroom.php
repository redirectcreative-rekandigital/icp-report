<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    protected $fillable = [
        'term_id', 'grade', 'name', 'homeroom_teacher', 'homeroom_id_number',
        'english_teacher', 'english_id_number', 'status', 'finalized_at', 'current_finalization_id',
    ];

    protected function casts(): array
    {
        return ['grade' => 'integer', 'finalized_at' => 'datetime'];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class)->orderBy('roll_no');
    }

    /** Only grade 6 gets the National Geographic placement level (revisi 23-11-2026 item 4a). */
    public const NATGEO_GRADE = 6;

    public function usesNatGeo(): bool
    {
        return $this->grade === self::NATGEO_GRADE;
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }

    public function hasScores(): bool
    {
        return Score::query()
            ->whereHas('enrollment', fn ($q) => $q->where('classroom_id', $this->id))
            ->whereAnyNotNull()
            ->exists();
    }
}
