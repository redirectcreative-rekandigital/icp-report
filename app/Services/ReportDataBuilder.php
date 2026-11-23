<?php

namespace App\Services;

use App\Domain\Cefr\Band;
use App\Domain\Cefr\CefrCalculator;
use App\Domain\Cefr\Level;
use App\Domain\Cefr\ScoringConfig;
use App\Domain\Cefr\Skill;
use App\Domain\Cefr\StudentResult;
use App\Exceptions\BusinessRuleException;
use App\Models\Classroom;
use App\Models\Descriptor;
use App\Models\Enrollment;
use App\Models\Finalization;
use App\Models\FinalResult;
use App\Models\LevelMeta;
use App\Models\Student;
use App\Support\ReportLayout;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * Everything one report page needs, identical for the on-screen preview and the PDF
 * (PRD-05 §4). Final classes read the finalisation snapshot + final_results; Draft classes
 * read live data and are watermarked DRAFT.
 */
class ReportDataBuilder
{
    public const LEVEL_FG = ['Pre-A1' => '#3B2A12', 'A1' => '#3B2A12', 'A2' => '#FFFFFF', 'B1' => '#FFFFFF', 'B2' => '#FFFFFF'];

    public const LEVEL_BG = ['Pre-A1' => '#F4A261', 'A1' => '#E9C46A', 'A2' => '#1F7A70', 'B1' => '#457B9D', 'B2' => '#6A4C93'];

    public function __construct(
        private Settings $settings,
        private ScoreService $scores,
        private RadarChartRenderer $radar,
    ) {}

    /** @return array<string, mixed> */
    public function build(Enrollment $enrollment, string $paper = 'f4', bool $forPreview = false, ?array $layoutOverride = null): array
    {
        $enrollment->loadMissing(['student', 'score', 'classroom.term.academicYear']);
        $classroom = $enrollment->classroom;

        $final = $classroom->isFinal()
            ? FinalResult::query()->where('enrollment_id', $enrollment->id)->where('is_current', true)->first()
            : null;

        $src = $final ? $this->fromSnapshot($final) : $this->live($enrollment, $classroom);
        $layout = ReportLayout::normalize($layoutOverride ?? $src['layout']);

        return $this->assemble($enrollment, $src, $layout, $paper, ! $final, $forPreview);
    }

    /** A sample report for Pengaturan → Rapor (no student needed). */
    public function sample(array $layout, string $paper): array
    {
        $config = new ScoringConfig(13, 20);
        $raw = ['listening' => 18, 'reading' => 21, 'writing' => 12, 'speaking' => 16];
        $src = [
            'config' => $config,
            'raw' => $raw,
            'result' => CefrCalculator::calculate($config, $raw),
            'natgeo' => 'A2',
            'comment' => null,
            'band_comments' => ScoreService::overallComments(),
            'level_meta' => LevelMeta::query()->orderBy('sort')->get()->toArray(),
            'descriptors' => Descriptor::query()->get()->toArray(),
            'school' => $this->schoolNow(),
            'classroom' => ['name' => '6A', 'grade' => 6, 'homeroom_teacher' => 'Homeroom Teacher', 'homeroom_id_number' => null, 'english_teacher' => 'English Teacher', 'english_id_number' => null],
            'term' => ['year' => '2026/2027', 'label' => 'Semester 1', 'report_date' => now()->toDateString()],
            'layout' => $layout,
            'finalized_at' => null,
        ];
        $student = new Enrollment;
        $student->setRelation('student', new Student(['name' => 'Kevin Wijaya', 'nis' => '5001']));

        return $this->assemble($student, $src, ReportLayout::normalize($layout), $paper, false, true, sample: true);
    }

    /** @return array<string, mixed> */
    private function fromSnapshot(FinalResult $r): array
    {
        $f = Finalization::query()->findOrFail($r->finalization_id);
        $snap = $f->config_snapshot;
        $config = ScoringConfig::fromArray($snap['scoring']);
        $raw = ['listening' => $r->listening, 'reading' => $r->reading, 'writing' => $r->writing, 'speaking' => $r->speaking];

        return [
            'config' => $config,
            'raw' => $raw,
            'result' => new StudentResult(
                ['listening' => $r->listening_level, 'reading' => $r->reading_level, 'writing' => $r->writing_level, 'speaking' => $r->speaking_level],
                $r->total,
                $r->overall_level,
                $r->strongest_skill,
            ),
            'natgeo' => $r->natgeo_level,
            'comment' => $r->comment_final,
            'band_comments' => $snap['band_comments'] ?? [],
            'level_meta' => $snap['level_meta'],
            'descriptors' => $snap['descriptors'],
            'school' => $snap['school'],
            'classroom' => $snap['classroom'],
            'term' => $snap['term'],
            'layout' => $snap['report_layout'] ?? null,
            'finalized_at' => $f->created_at,
        ];
    }

    /** @return array<string, mixed> */
    private function live(Enrollment $e, Classroom $c): array
    {
        $config = $this->scores->config($c);
        $raw = $this->scores->scorePayload($e->score)['scores'];
        $result = CefrCalculator::calculate($config, $raw);

        if (! $result->isComplete()) {
            throw new BusinessRuleException("Nilai {$e->student->name} belum lengkap; rapor belum dapat dibuat.", 'INCOMPLETE');
        }

        return [
            'config' => $config,
            'raw' => $raw,
            'result' => $result,
            'natgeo' => $c->usesNatGeo() ? $e->score?->natgeo_level : null,
            'comment' => $e->score?->teacher_comment,
            'band_comments' => ScoreService::overallComments(),
            'level_meta' => LevelMeta::query()->orderBy('sort')->get()->toArray(),
            'descriptors' => Descriptor::query()->get()->toArray(),
            'school' => $this->schoolNow(),
            'classroom' => $c->only(['name', 'grade', 'homeroom_teacher', 'homeroom_id_number', 'english_teacher', 'english_id_number']),
            'term' => [
                'year' => $c->term->academicYear->name,
                'label' => $c->term->label,
                'report_date' => $c->term->report_date?->toDateString(),
            ],
            'layout' => $this->settings->get('report_layout'),
            'finalized_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function schoolNow(): array
    {
        $s = $this->settings;

        return [
            'name' => $s->get('school_name'),
            'address' => $s->get('school_address'),
            'program' => $s->get('program_name'),
            'partner' => $s->get('partner_name'),
            'city' => $s->get('city'),
            'principal_name' => $s->get('principal_name'),
            'principal_id_label' => $s->get('principal_id_label'),
            'principal_id_number' => $s->get('principal_id_number'),
            'school_logo_path' => $s->get('school_logo_path'),
            'second_logo_path' => $s->get('second_logo_path'),
            'partner_logo_path' => $s->get('partner_logo_path'),
        ];
    }

    /** @return array<string, mixed> */
    private function assemble(Enrollment $e, array $src, array $layout, string $paper, bool $draft, bool $forPreview, bool $sample = false): array
    {
        /** @var StudentResult $res */
        $res = $src['result'];
        $config = $src['config'];
        $meta = collect($src['level_meta'])->keyBy('level');
        $desc = collect($src['descriptors'])->keyBy(fn ($d) => $d['level'].'|'.$d['skill']);
        $school = $src['school'];
        $class = $src['classroom'];
        $sections = $layout['sections'];
        $warnings = [];

        $skills = [];
        foreach (Skill::values() as $s) {
            $lvl = $res->level[$s];
            $skills[] = [
                'key' => $s,
                'label' => Skill::from($s)->label(),
                'score' => self::num($src['raw'][$s]),
                'raw' => $src['raw'][$s],
                'max' => self::num(ScoringConfig::MAX),
                'level' => $lvl,
                'bg' => Band::BG[$lvl],
                'fg' => Band::FG[$lvl],
                'can_do' => $desc[$lvl.'|'.$s]['can_do'] ?? '',
                'next_step' => $desc[$lvl.'|'.$s]['next_step'] ?? '',
            ];
        }

        // CEFR map: grade 6 only, marked at the National Geographic placement level.
        $natgeo = (int) $class['grade'] === Classroom::NATGEO_GRADE ? ($src['natgeo'] ?? null) : null;
        $levels = [];
        foreach (Level::cases() as $l) {
            $m = $meta[$l->value] ?? [];
            $levels[] = [
                'level' => $l->value,
                'short_name' => $m['short_name'] ?? $l->value,
                'cambridge' => $m['cambridge'] ?? '',
                'bg' => self::LEVEL_BG[$l->value],
                'fg' => self::LEVEL_FG[$l->value],
                'current' => $l->value === $natgeo,
            ];
        }
        $sections['level_map'] = $sections['level_map'] && (int) $class['grade'] === Classroom::NATGEO_GRADE;

        $comment = $src['comment'] ?? null;
        if ($comment === null || $draft) {
            $comment = CefrCalculator::reportComment(
                $res,
                $e->student->name,
                $draft ? $src['comment'] : null,
                $src['band_comments'],
                $sections['strongest_skill'],
            );
        }

        // Logos (data URIs so DomPDF needs no file access rules).
        $logo = fn (?string $path) => $path && Storage::disk('local')->exists($path)
            ? 'data:'.(Storage::disk('local')->mimeType($path) ?: 'image/png').';base64,'.base64_encode(Storage::disk('local')->get($path))
            : null;
        $second = $layout['header']['show_muhammadiyah_logo'] ? $logo($school['second_logo_path'] ?? null) : null;
        if ($layout['header']['show_muhammadiyah_logo'] && ! $second) {
            $warnings[] = 'Logo Muhammadiyah belum diunggah (Pengaturan → Sekolah).';
        }

        $idLabel = $layout['id_label'];
        $idLine = fn (?string $number) => $idLabel !== 'none' && $number ? "{$idLabel}. {$number}" : null;
        $signatures = array_map(fn ($s) => [
            'label' => $s['label'],
            'name' => match ($s['role']) {
                'parent' => null,
                'homeroom' => $class['homeroom_teacher'] ?? null,
                'english' => $class['english_teacher'] ?? null,
                'principal' => $school['principal_name'] ?? null,
            },
            'id_line' => match ($s['role']) {
                'homeroom' => $idLine($class['homeroom_id_number'] ?? null),
                'english' => $idLine($class['english_id_number'] ?? null),
                'principal' => $idLine($school['principal_id_number'] ?? null),
                default => null,
            },
        ], ReportLayout::printedSignatures($layout));

        $reportDate = $src['term']['report_date'] ?? null;
        $levelList = implode('  •  ', array_map(fn ($l) => "{$l['level']} {$l['short_name']}", $levels));
        $footnote = (require database_path('seeders/data/cefr_content.php'))['report_footnote'];

        $name = $e->student->name;
        $commentLen = mb_strlen((string) $comment);

        return [
            'paper' => $paper,
            'draft' => $draft,
            'sample' => $sample,
            'preview' => $forPreview,
            'warnings' => $forPreview ? $warnings : [],
            'layout' => $layout,
            'sections' => $sections,
            'theme' => $layout['theme'],
            'school' => [
                'name' => mb_strtoupper((string) $school['name']),
                'address' => $school['address'] ?? '',
                'program' => mb_strtoupper((string) ($school['program'] ?? '')),
                'partner' => $school['partner'] ?? '',
                'extra_line' => $layout['header']['extra_line'],
            ],
            'logos' => [
                'school' => $logo($school['school_logo_path'] ?? null),
                'second' => $second,
                'partner' => $logo($school['partner_logo_path'] ?? null),
            ],
            'title' => $layout['header']['title'],
            'subtitle' => "CEFR-Aligned Assessment  •  Academic Year {$src['term']['year']}  •  {$src['term']['label']}",
            'student' => ['name' => $name, 'nis' => $e->student->nis, 'class' => $class['name'], 'grade' => $class['grade']],
            'term' => $src['term'],
            'teachers' => ['homeroom' => $class['homeroom_teacher'] ?? '', 'english' => $class['english_teacher'] ?? ''],
            'skills' => $skills,
            'overall' => [
                'total' => self::num($res->total),
                'max' => self::num(ScoringConfig::MAX * 4),
                'level' => $res->overallLevel,
                'bg' => Band::BG[$res->overallLevel],
                'fg' => Band::FG[$res->overallLevel],
            ],
            'natgeo' => $natgeo,
            'levels' => $levels,
            'comment' => $comment,
            'place_date' => (($school['city'] ?? '') ? "{$school['city']}, " : '').($reportDate ? CarbonImmutable::parse($reportDate)->locale('en')->translatedFormat('j F Y') : '.............................'),
            'signatures' => $signatures,
            'footnote' => "{$footnote}  {$levelList}",
            // shortcut: radar fed with score/25 until stage R2 replaces it with the line chart.
            'radar' => $sections['radar'] ? $this->radar->render(array_map(fn ($v) => $v / ScoringConfig::MAX * 100, array_values($src['raw'])), $layout['theme']['primary']) : null,
            'finalized_at' => $src['finalized_at']?->toIso8601String(),
            // Auto-shrink long texts so the page stays one page (PRD-05 §6, §8).
            'fs' => [
                'school' => mb_strlen((string) $school['name']) > 40 ? 12 : 15,
                'name' => mb_strlen($name) > 32 ? 8.5 : 10,
                'comment' => $commentLen > 450 ? 8 : ($commentLen > 300 ? 8.5 : 9),
            ],
        ];
    }

    private static function num(?float $v): string
    {
        return $v === null ? '–' : rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
    }

    /** Report_{NIS}_{Nama}_{2026-2027}_S1_F4.pdf */
    public static function filename(array $data): string
    {
        $name = preg_replace('/[^A-Za-z0-9]+/', '_', $data['student']['name']);
        $year = str_replace('/', '-', $data['term']['year']);
        $sem = preg_replace('/\D/', '', $data['term']['label']) ?: '1';

        return 'Report_'.$data['student']['nis'].'_'.trim($name, '_')."_{$year}_S{$sem}_".strtoupper($data['paper']).'.pdf';
    }
}
