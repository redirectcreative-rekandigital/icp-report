<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    public const SKILLS = ['listening', 'reading', 'writing', 'speaking'];

    protected $fillable = ['enrollment_id', 'listening', 'reading', 'writing', 'speaking', 'teacher_comment', 'version'];

    protected function casts(): array
    {
        return [
            'listening' => 'float',
            'reading' => 'float',
            'writing' => 'float',
            'speaking' => 'float',
            'version' => 'integer',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** Rows holding at least one skill score. */
    public function scopeWhereAnyNotNull(Builder $q): Builder
    {
        return $q->where(function ($w) {
            foreach (self::SKILLS as $s) {
                $w->orWhereNotNull($s);
            }
        });
    }

    /** Rows with all four skill scores. */
    public function scopeComplete(Builder $q): Builder
    {
        return $q->where(function ($w) {
            foreach (self::SKILLS as $s) {
                $w->whereNotNull($s);
            }
        });
    }
}
