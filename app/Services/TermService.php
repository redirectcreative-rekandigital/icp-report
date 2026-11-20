<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\AcademicYear;
use App\Models\ScoringConfig;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

/** Academic years & semesters (PRD-03 §3.1). */
class TermService
{
    public const GRADES = [3, 4, 5, 6];

    public function __construct(private ActivityLogger $logger) {}

    /**
     * Creates a semester and its grade 3–6 scoring configs, copied from the most recent
     * period that has configs (or the Excel defaults).
     *
     * @return array{0: Term, 1: ?string} the term and the label of the copy source (null = defaults)
     */
    public function createTerm(AcademicYear $year, int $semester, ?string $reportDate): array
    {
        return DB::transaction(function () use ($year, $semester, $reportDate) {
            $source = Term::query()->chronological()->whereHas('scoringConfigs')->with('academicYear')->first();

            $term = $year->terms()->create([
                'semester' => $semester,
                'label' => "Semester {$semester}",
                'report_date' => $reportDate,
                // The very first period becomes active automatically.
                'is_active' => ! Term::query()->exists(),
            ]);

            $this->createScoringConfigs($term, $source);
            $term->load('academicYear');

            $this->logger->log('term.created', 'data', "Periode {$term->fullLabel()} dibuat.", $term);

            return [$term, $source?->fullLabel()];
        });
    }

    private function createScoringConfigs(Term $term, ?Term $source): void
    {
        $sourceConfigs = $source?->scoringConfigs()->get()->keyBy('grade');
        $defaults = (require database_path('seeders/data/cefr_content.php'))['scoring_defaults'];

        foreach (self::GRADES as $grade) {
            $from = $sourceConfigs?->get($grade);

            $values = $from
                ? $from->only(ScoringConfig::COPY_FIELDS)
                : [
                    'thresholds' => $defaults[$grade]['thresholds'],
                    'expected_min' => $defaults[$grade]['expected_min'],
                    'expected_max' => $defaults[$grade]['expected_max'],
                ];

            $term->scoringConfigs()->create(['grade' => $grade] + $values);
        }
    }

    public function activate(Term $term): void
    {
        DB::transaction(function () use ($term) {
            Term::query()->where('is_active', true)->update(['is_active' => false]);
            $term->update(['is_active' => true]);
        });

        $term->loadMissing('academicYear');
        $this->logger->log('term.activated', 'data', "Periode aktif diganti ke {$term->fullLabel()}.", $term);
    }

    public function delete(Term $term): void
    {
        $term->loadMissing('academicYear');

        if ($term->hasScores()) {
            throw new BusinessRuleException('Periode ini sudah memiliki nilai sehingga tidak dapat dihapus.');
        }

        if ($term->is_active && Term::query()->whereKeyNot($term->id)->exists()) {
            throw new BusinessRuleException('Periode aktif tidak dapat dihapus. Jadikan periode lain aktif terlebih dahulu.');
        }

        $label = $term->fullLabel();
        $term->delete(); // cascades classes, enrollments, configs
        $this->logger->log('term.deleted', 'data', "Periode {$label} dihapus.");
    }

    public function deleteYear(AcademicYear $year): void
    {
        if ($year->terms()->exists()) {
            throw new BusinessRuleException("Tahun ajaran {$year->name} masih memiliki semester. Hapus semesternya terlebih dahulu.");
        }

        $year->delete();
        $this->logger->log('year.deleted', 'data', "Tahun ajaran {$year->name} dihapus.");
    }
}
