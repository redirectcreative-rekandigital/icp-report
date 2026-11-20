<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** "Periode" = academic year + semester. */
class Term extends Model
{
    protected $fillable = ['academic_year_id', 'semester', 'label', 'report_date', 'is_active'];

    protected function casts(): array
    {
        return ['report_date' => 'date', 'is_active' => 'boolean', 'semester' => 'integer'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function scoringConfigs(): HasMany
    {
        return $this->hasMany(ScoringConfig::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public static function active(): ?self
    {
        return static::query()->with('academicYear')->where('is_active', true)->first();
    }

    /** Newest first by default: academic year name, then semester. */
    public function scopeChronological(Builder $q, string $dir = 'desc'): Builder
    {
        return $q->join('academic_years', 'academic_years.id', '=', 'terms.academic_year_id')
            ->orderBy('academic_years.name', $dir)
            ->orderBy('terms.semester', $dir)
            ->select('terms.*');
    }

    /** "2026/2027 • Semester 1" */
    public function fullLabel(): string
    {
        return $this->academicYear->name.' • '.$this->label;
    }

    /** "2026/27 S1" */
    public function shortLabel(): string
    {
        [$a, $b] = explode('/', $this->academicYear->name);

        return $a.'/'.substr($b, -2).' S'.$this->semester;
    }

    /** "2026-2027_S1" for file names. */
    public function fileSlug(): string
    {
        return str_replace('/', '-', $this->academicYear->name).'_S'.$this->semester;
    }

    public function hasScores(): bool
    {
        return Score::query()
            ->whereHas('enrollment', fn ($q) => $q->where('term_id', $this->id))
            ->whereAnyNotNull()
            ->exists();
    }
}
