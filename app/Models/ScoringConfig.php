<?php

namespace App\Models;

use App\Domain\Cefr\Level;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringConfig extends Model
{
    public const COPY_FIELDS = [
        'max_listening', 'max_reading', 'max_writing', 'max_speaking',
        'weight_listening', 'weight_reading', 'weight_writing', 'weight_speaking',
        'thresholds', 'expected_min', 'expected_max',
    ];

    protected $fillable = ['term_id', 'grade', ...self::COPY_FIELDS];

    protected function casts(): array
    {
        return ['grade' => 'integer'];
    }

    /**
     * Always Pre-A1 → B2, whatever order the database returns (MySQL JSON columns
     * re-sort object keys). Missing levels are null (= not measured).
     */
    protected function thresholds(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                $stored = $value ? json_decode($value, true) : [];

                return array_combine(Level::values(), array_map(fn ($l) => $stored[$l] ?? null, Level::values()));
            },
            set: fn (array $value) => json_encode($value),
        );
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
