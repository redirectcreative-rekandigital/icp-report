<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Frozen per-student result written at finalisation (PRD-01 §4.1). */
class FinalResult extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'listening' => 'float', 'reading' => 'float', 'writing' => 'float', 'speaking' => 'float',
            'listening_pct' => 'float', 'reading_pct' => 'float', 'writing_pct' => 'float', 'speaking_pct' => 'float',
            'overall_pct' => 'float',
            'is_current' => 'boolean',
            'grade' => 'integer',
        ];
    }

    public function finalization(): BelongsTo
    {
        return $this->belongsTo(Finalization::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
