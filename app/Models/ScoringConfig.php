<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Band thresholds per grade per period (see App\Domain\Cefr\ScoringConfig). */
class ScoringConfig extends Model
{
    public const COPY_FIELDS = ['min_intermediate', 'min_advanced'];

    protected $fillable = ['term_id', 'grade', ...self::COPY_FIELDS];

    protected function casts(): array
    {
        return ['grade' => 'integer', 'min_intermediate' => 'float', 'min_advanced' => 'float'];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
