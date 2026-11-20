<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Cefr\CefrCalculator;
use App\Domain\Cefr\Level;
use App\Domain\Cefr\ScoringConfig as Config;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PeriodController;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\ScoringConfig;
use App\Models\Term;
use App\Services\ActivityLogger;
use App\Services\ScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Pengaturan → Penilaian: max, weights, thresholds, expected range per grade (PRD-04 §5.3). */
class ScoringSettingsController extends Controller
{
    private const SKILLS = ['listening', 'reading', 'writing', 'speaking'];

    public function edit(Request $request): Response
    {
        $term = $request->integer('term')
            ? Term::query()->with('academicYear')->find($request->integer('term'))
            : Term::active();

        $configs = $term ? $term->scoringConfigs()->orderBy('grade')->get()->map(fn (ScoringConfig $c) => [
            'id' => $c->id,
            'grade' => $c->grade,
            ...Config::fromModel($c)->toArray(),
        ]) : [];

        return Inertia::render('Settings/Scoring', [
            'term' => $term ? ['id' => $term->id, 'label' => $term->fullLabel()] : null,
            'terms' => PeriodController::termOptions(),
            'configs' => $configs,
            'finalClasses' => $term ? $term->classrooms()->where('status', 'final')->pluck('name') : [],
        ]);
    }

    public function update(Request $request, ScoringConfig $config, ActivityLogger $logger): RedirectResponse
    {
        $values = $this->validated($request);
        $this->guardMaxAgainstScores($config, $values);

        $config->update($values);
        $config->load('term.academicYear');
        $logger->log(
            'settings.scoring_updated',
            'pengaturan',
            "Pengaturan penilaian Kelas {$config->grade} ({$config->term->fullLabel()}) diubah.",
            $config,
        );

        $hasFinal = Classroom::query()->where('term_id', $config->term_id)->where('grade', $config->grade)->where('status', 'final')->exists();

        return back()->with('success', "Pengaturan Kelas {$config->grade} tersimpan."
            .($hasFinal ? ' Rapor kelas yang sudah final tidak berubah.' : ''));
    }

    /** Which students in Draft classes would change level/status with the proposed values. */
    public function impact(Request $request, ScoringConfig $config): JsonResponse
    {
        $values = $this->validated($request);
        $before = Config::fromModel($config);
        $after = Config::fromArray([
            'max' => array_combine(self::SKILLS, array_map(fn ($s) => $values["max_{$s}"], self::SKILLS)),
            'weight' => array_combine(self::SKILLS, array_map(fn ($s) => $values["weight_{$s}"], self::SKILLS)),
            'thresholds' => $values['thresholds'],
            'expected_min' => $values['expected_min'],
            'expected_max' => $values['expected_max'],
        ]);

        $changes = [];
        $enrollments = Enrollment::query()
            ->with(['student', 'score', 'classroom'])
            ->whereHas('classroom', fn ($q) => $q->where('term_id', $config->term_id)->where('grade', $config->grade)->where('status', 'draft'))
            ->get();

        $scores = app(ScoreService::class);
        foreach ($enrollments as $e) {
            $raw = $scores->scorePayload($e->score)['scores'];
            $a = CefrCalculator::calculate($before, $raw);
            $b = CefrCalculator::calculate($after, $raw);

            if ($a->overallLevel !== $b->overallLevel || $a->status !== $b->status || $a->level !== $b->level) {
                $changes[] = [
                    'name' => $e->student->name,
                    'class' => $e->classroom->name,
                    'from' => $a->overallLevel,
                    'to' => $b->overallLevel,
                    'status_from' => $a->status,
                    'status_to' => $b->status,
                ];
            }
        }

        return response()->json(['count' => count($changes), 'students' => array_slice($changes, 0, 30)]);
    }

    /** @return array<string, mixed> column values for scoring_configs */
    private function validated(Request $request): array
    {
        $levels = Level::values();
        $rules = [
            'thresholds' => ['required', 'array'],
            'expected_min' => ['required', Rule::in($levels)],
            'expected_max' => ['required', Rule::in($levels)],
        ];
        foreach (self::SKILLS as $s) {
            $rules["max.{$s}"] = ['required', 'numeric', 'min:1', 'max:100'];
            $rules["weight.{$s}"] = ['required', 'numeric', 'min:0', 'max:100'];
        }
        foreach ($levels as $l) {
            $rules["thresholds.{$l}"] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        $data = $request->validate($rules, [
            'max.*.required' => 'Nilai maksimum wajib diisi.',
            'max.*.min' => 'Nilai maksimum minimal 1.',
            'max.*.max' => 'Nilai maksimum paling besar 100.',
            'weight.*.min' => 'Bobot 0–100.',
            'weight.*.max' => 'Bobot 0–100.',
            'thresholds.*.max' => 'Ambang batas maksimal 100%.',
        ]);

        $errors = [];

        if (array_sum(array_map('floatval', $data['weight'])) <= 0) {
            $errors['weight'] = 'Total bobot harus lebih dari 0.';
        }

        // Pre-A1 is always 0; filled thresholds must strictly increase; nothing after an empty level.
        $thresholds = ['Pre-A1' => 0.0];
        $previous = 0.0;
        $gap = false;
        foreach (array_slice($levels, 1) as $level) {
            $v = $data['thresholds'][$level] ?? null;
            $v = ($v === null || $v === '') ? null : (float) $v;

            if ($v === null) {
                $gap = true;
            } elseif ($gap) {
                $errors["thresholds.{$level}"] = "{$level} harus kosong karena level sebelumnya kosong.";
            } elseif ($v <= $previous) {
                $errors["thresholds.{$level}"] = "Ambang {$level} harus lebih besar dari level sebelumnya ({$previous}%).";
            } else {
                $previous = $v;
            }

            $thresholds[$level] = $v;
        }

        $min = Level::from($data['expected_min']);
        $max = Level::from($data['expected_max']);
        if ($min->index() > $max->index()) {
            $errors['expected_min'] = 'Level harapan terendah tidak boleh di atas level tertinggi.';
        }
        foreach (['expected_min' => $min, 'expected_max' => $max] as $key => $level) {
            if ($thresholds[$level->value] === null) {
                $errors[$key] ??= "Level {$level->value} tidak diukur (ambang batasnya kosong).";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $values = ['thresholds' => $thresholds, 'expected_min' => $min->value, 'expected_max' => $max->value];
        foreach (self::SKILLS as $s) {
            $values["max_{$s}"] = (float) $data['max'][$s];
            $values["weight_{$s}"] = (float) $data['weight'][$s];
        }

        return $values;
    }

    /** A lower maximum must not leave existing scores above it. */
    private function guardMaxAgainstScores(ScoringConfig $config, array $values): void
    {
        $errors = [];

        foreach (self::SKILLS as $s) {
            $highest = Score::query()
                ->whereHas('enrollment.classroom', fn ($q) => $q->where('term_id', $config->term_id)->where('grade', $config->grade))
                ->max($s);

            if ($highest !== null && (float) $highest > $values["max_{$s}"]) {
                $h = rtrim(rtrim(number_format((float) $highest, 1, ',', ''), '0'), ',');
                $errors["max.{$s}"] = 'Ada nilai '.ucfirst($s)." {$h} yang melebihi maksimum baru.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
