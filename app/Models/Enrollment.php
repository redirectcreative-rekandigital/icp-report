<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Membership of one student in one class for one period. */
class Enrollment extends Model
{
    protected $fillable = ['classroom_id', 'student_id', 'term_id', 'roll_no'];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function score(): HasOne
    {
        return $this->hasOne(Score::class);
    }

    public function hasScores(): bool
    {
        return $this->score()->whereAnyNotNull()->exists();
    }
}
