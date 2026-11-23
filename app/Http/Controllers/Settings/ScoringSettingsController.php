<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Cefr\CefrCalculator;
use App\Domain\Cefr\ScoringConfig as Config;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PeriodController;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\ScoringConfig;
use App\Models\Term;
use App\Services\ActivityLogger;
use App\Services\ScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Pengaturan → Penilaian: Beginner / Intermediate / Advanced thresholds per grade. */
class ScoringSettingsController extends Controller
{
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
        $config->update(self::validatedThresholds($request));
        $config->load('term.academicYear');
        $logger->log(
            'settings.scoring_updated',
            'pengaturan',
            "Batas level Kelas {$config->grade} ({$config->term->fullLabel()}) diubah.",
            $config,
        );

        $hasFinal = Classroom::query()->where('term_id', $config->term_id)->where('grade', $config->grade)->where('status', 'final')->exists();

        return back()->with('success', "Pengaturan Kelas {$config->grade} tersimpan."
            .($hasFinal ? ' Rapor kelas yang sudah final tidak berubah.' : ''));
    }

    /** Which students in Draft classes would change band with the proposed values. */
    public function impact(Request $request, ScoringConfig $config): JsonResponse
    {
        $values = self::validatedThresholds($request);
        $before = Config::fromModel($config);
        $after = new Config($values['min_intermediate'], $values['min_advanced']);

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

            if ($a->overallLevel !== $b->overallLevel || $a->level !== $b->level) {
                $changes[] = [
                    'name' => $e->student->name,
                    'class' => $e->classroom->name,
                    'from' => $a->overallLevel,
                    'to' => $b->overallLevel,
                ];
            }
        }

        return response()->json(['count' => count($changes), 'students' => array_slice($changes, 0, 30)]);
    }

    /** @return array{min_intermediate: float, min_advanced: float} */
    /** Also used by the setup wizard. @return array{min_intermediate: float, min_advanced: float} */
    public static function validatedThresholds(Request $request): array
    {
        $range = ['required', 'numeric', 'min:'.Config::MIN, 'max:'.Config::MAX, 'multiple_of:0.5'];
        $data = $request->validate([
            'intermediate' => $range,
            'advanced' => $range,
        ], [
            '*.required' => 'Batas wajib diisi.',
            '*.min' => 'Batas harus antara 5 dan 25.',
            '*.max' => 'Batas harus antara 5 dan 25.',
            '*.multiple_of' => 'Batas harus bulat atau kelipatan 0,5.',
        ]);

        $intermediate = (float) $data['intermediate'];
        $advanced = (float) $data['advanced'];

        if ($intermediate <= Config::MIN) {
            throw ValidationException::withMessages(['intermediate' => 'Batas Intermediate harus lebih dari 5, supaya level Beginner tetap ada.']);
        }
        if ($advanced <= $intermediate) {
            throw ValidationException::withMessages(['advanced' => 'Batas Advanced harus lebih besar dari batas Intermediate.']);
        }

        return ['min_intermediate' => $intermediate, 'min_advanced' => $advanced];
    }
}
