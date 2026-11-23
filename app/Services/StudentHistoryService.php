<?php

namespace App\Services;

use App\Domain\Cefr\Band;
use App\Domain\Cefr\CefrCalculator;
use App\Domain\Cefr\Skill;
use App\Models\Enrollment;
use App\Models\FinalResult;
use App\Models\Student;
use Illuminate\Support\Collection;

/** A student's level per period (PRD-06 §4). History is keyed by student, so class moves don't break it. */
class StudentHistoryService
{
    public function __construct(private ScoreService $scores) {}

    /** @return list<array<string, mixed>> oldest first */
    public function points(Student $student): array
    {
        $enrollments = $student->enrollments()->with(['classroom', 'term.academicYear', 'score'])->get()
            ->sortBy(fn (Enrollment $e) => $e->term->academicYear->name.'-'.$e->term->semester)->values();
        $finals = FinalResult::query()->where('student_id', $student->id)->where('is_current', true)->get()->keyBy('enrollment_id');

        $points = [];
        foreach ($enrollments as $e) {
            $c = $e->classroom;
            $final = $finals->get($e->id);

            if ($final) {
                $scores = array_combine(Skill::values(), array_map(fn ($s) => $final->{$s}, Skill::values()));
                $levels = array_combine(Skill::values(), array_map(fn ($s) => $final->{"{$s}_level"}, Skill::values()));
                $total = $final->total;
                $overallLevel = $final->overall_level;
                $natgeo = $final->natgeo_level;
                $source = $c->isFinal() ? 'final' : 'correcting';
            } else {
                $payload = $this->scores->scorePayload($e->score);
                $r = CefrCalculator::calculate($this->scores->config($c), $payload['scores']);

                if (! $r->isComplete()) {
                    continue;
                }

                [$scores, $levels, $total, $overallLevel, $natgeo, $source] = [$payload['scores'], $r->level, $r->total, $r->overallLevel, $c->usesNatGeo() ? $payload['natgeo'] : null, 'draft'];
            }

            $points[] = [
                'enrollment_id' => $e->id,
                'term' => $e->term->shortLabel(),
                'term_full' => $e->term->fullLabel(),
                'semester' => $e->term->semester,
                'classroom' => ['id' => $c->id, 'name' => $c->name, 'grade' => $c->grade],
                'scores' => $scores,
                'levels' => $levels,
                'total' => round($total, 1),
                'overall_level' => $overallLevel,
                'natgeo' => $natgeo,
                'source' => $source,
                'pdf_url' => $c->isFinal() ? route('reports.pdf', [$c, $e]) : null,
            ];
        }

        // Level change vs. the previous point.
        foreach ($points as $i => &$p) {
            $prev = $points[$i - 1] ?? null;
            $p['change'] = $prev ? $this->direction($prev['overall_level'], $p['overall_level']) : null;
            $p['previous_level'] = $prev['overall_level'] ?? null;
        }

        return $points;
    }

    /** "Kevin naik dari A1 (Kelas 4, Semester 2) ke A2 (Kelas 5, Semester 1). …" */
    public function narrative(Student $student, array $points): ?string
    {
        $final = collect($points)->whereIn('source', ['final', 'correcting'])->values();

        if ($final->count() < 2) {
            return null;
        }

        [$a, $b] = [$final[$final->count() - 2], $final->last()];
        $first = explode(' ', trim($student->name))[0];
        $where = fn ($p) => "Kelas {$p['classroom']['grade']}, Semester {$p['semester']}";

        $sentence = match ($this->direction($a['overall_level'], $b['overall_level'])) {
            'up' => "{$first} naik dari {$a['overall_level']} ({$where($a)}) ke {$b['overall_level']} ({$where($b)}).",
            'down' => "{$first} turun dari {$a['overall_level']} ({$where($a)}) ke {$b['overall_level']} ({$where($b)}).",
            default => "{$first} tetap di level {$b['overall_level']} dari {$where($a)} ke {$where($b)}.",
        };

        $gains = collect(Skill::values())->mapWithKeys(fn ($s) => [$s => ($b['scores'][$s] ?? 0) - ($a['scores'][$s] ?? 0)]);
        $best = $gains->sortDesc()->keys()->first();

        if ($gains[$best] > 0) {
            $points = str_replace('.', ',', (string) round($gains[$best]));
            $sentence .= ' Skill dengan kenaikan terbesar: '.Skill::from($best)->label()." (+{$points} poin).";
        }

        if ($a['classroom']['grade'] !== $b['classroom']['grade']) {
            $sentence .= ' Catatan: ambang batas tiap jenjang bisa berbeda, jadi bandingkan levelnya, bukan angkanya.';
        }

        return $sentence;
    }

    /** Latest final overall level per student, for the student list. */
    public static function latestFinalLevels(Collection $studentIds): array
    {
        return FinalResult::query()
            ->join('terms', 'terms.id', '=', 'final_results.term_id')
            ->join('academic_years', 'academic_years.id', '=', 'terms.academic_year_id')
            ->whereIn('final_results.student_id', $studentIds)
            ->where('final_results.is_current', true)
            ->orderBy('academic_years.name')->orderBy('terms.semester')
            ->get(['final_results.student_id', 'final_results.overall_level'])
            ->pluck('overall_level', 'student_id') // later periods overwrite earlier ones
            ->all();
    }

    private function direction(?string $from, ?string $to): string
    {
        $a = $from ? Band::from($from)->index() : 0;
        $b = $to ? Band::from($to)->index() : 0;

        return $b > $a ? 'up' : ($b < $a ? 'down' : 'same');
    }
}
