<?php

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Score;
use App\Models\Student;
use App\Models\Term;
use App\Services\FinalizationService;
use App\Services\TermService;
use Database\Seeders\DemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->completeSetup();
    $this->withPinSession();
    $this->seed(DemoSeeder::class); // 2026/2027 S1, 8 classes, 22 students (20 complete)
    $this->term = Term::active();
});

it('shows KPIs for the active period', function () {
    $this->get('/')->assertInertia(fn (Assert $p) => $p
        ->component('Dashboard')
        ->where('kpis.assessed', 20)
        ->where('kpis.students', 22)
        ->where('kpis.final', 0)
        ->where('kpis.classes', 8)
        ->where('kpis.average', fn ($v) => $v >= 20 && $v <= 100) // total score, not %
        ->where('kpis.intermediate_or_above', fn ($v) => $v >= 0 && $v <= 100)
        ->has('progress', 8)
        ->missing('charts'));
});

it('loads charts and highlights as deferred props', function () {
    $this->get('/')->assertInertia(fn (Assert $p) => $p
        ->missing('charts')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('charts.byClass', 8)
            ->where('charts.byClass.4.class', '5A')
            ->where('charts.byClass.4.levels.Advanced', ['Laras Anindya'])
            ->where('charts.byClass.4.levels.Intermediate', ['Kevin Wijaya'])
            ->where('charts.byClass.4.skills.reading', fn ($v) => $v >= 5 && $v <= 25)
            ->where('charts.natgeo', ['Pre-A1' => 1, 'A1' => 1, 'A2' => 2, 'B1' => 1, 'B2' => 0, 'empty' => 0])
            ->has('charts.byGrade', 4)
            ->has('charts.bySkill', 5)
            ->where('charts.bySkill.0.skill', 'listening')
            ->where('charts.bySkill.4.skill', 'total')
            ->where('charts.bySkill.4.levels', fn ($l) => collect($l)->sum() === 20) // every assessed student
            ->has('charts.insights', fn (Assert $i) => $i->etc())));
});

it('filters by grade', function () {
    $this->get('/?grade=5')->assertInertia(fn (Assert $p) => $p
        ->where('kpis.students', 7)
        ->where('kpis.classes', 2)
        ->has('progress', 2));
});

it('shows only final classes when asked, including un-finalised ones as "sedang dikoreksi"', function () {
    $c3a = Classroom::where('name', '3A')->first();
    app(FinalizationService::class)->finalize($c3a);

    $this->get('/?final_only=1')->assertInertia(fn (Assert $p) => $p
        ->where('kpis.assessed', 3)
        ->where('kpis.final', 1)
        ->loadDeferredProps(fn (Assert $r) => $r->has('charts.byClass', 1)->where('charts.byClass.0.correcting', false)));

    app(FinalizationService::class)->unfinalize($c3a->fresh(), 'Koreksi nilai Speaking');

    $this->get('/?final_only=1')->assertInertia(fn (Assert $p) => $p
        ->where('kpis.assessed', 3)
        ->loadDeferredProps(fn (Assert $r) => $r->where('charts.byClass.0.correcting', true)));
});

it('updates the recap immediately after an autosave (cache cleared)', function () {
    $this->get('/')->assertInertia(fn (Assert $p) => $p->where('kpis.assessed', 20));

    $wulan = Enrollment::whereHas('student', fn ($q) => $q->where('nipd', '5006'))->first();
    $class = $wulan->classroom;
    $v = 0;
    foreach (['listening' => 15, 'reading' => 15, 'writing' => 15, 'speaking' => 15] as $field => $value) {
        $this->patchJson("/kelas/{$class->id}/skor/{$wulan->id}", compact('field', 'value') + ['version' => $v++])->assertOk();
    }

    $this->get('/')->assertInertia(fn (Assert $p) => $p->where('kpis.assessed', 21));
});

it('compares two periods by level share, same students or all', function () {
    // Previous period with final results for a few of the same students.
    [$prev] = app(TermService::class)->createTerm(AcademicYear::create(['name' => '2025/2026']), 2, null);
    $old = Classroom::create(['term_id' => $prev->id, 'grade' => 4, 'name' => '4A']);
    foreach (['5001' => [10, 10, 10, 10], '5002' => [16, 16, 16, 16]] as $nipd => $s) {
        $e = Enrollment::create(['classroom_id' => $old->id, 'student_id' => Student::where('nipd', $nipd)->value('id'), 'term_id' => $prev->id]);
        Score::create(['enrollment_id' => $e->id] + array_combine(['listening', 'reading', 'writing', 'speaking'], $s));
    }
    app(FinalizationService::class)->finalize($old);

    $c5a = Classroom::where(['term_id' => $this->term->id, 'name' => '5A'])->first();
    Enrollment::where('classroom_id', $c5a->id)->whereHas('student', fn ($q) => $q->whereIn('nipd', ['5006', '5007']))->get()
        ->each(fn ($e) => Score::create(['enrollment_id' => $e->id, 'listening' => 12, 'reading' => 12, 'writing' => 12, 'speaking' => 12]));
    app(FinalizationService::class)->finalize($c5a);

    $this->get("/?tab=perbandingan&a={$prev->id}&b={$this->term->id}&cohort=same")->assertInertia(fn (Assert $p) => $p
        ->where('comparison.a.n', 2)
        ->where('comparison.b.n', 2)
        ->where('comparison.moves', ['up' => 2, 'same' => 0, 'down' => 0])); // Kevin Beginner→Intermediate, Laras Intermediate→Advanced

    $this->get("/?tab=perbandingan&a={$prev->id}&b={$this->term->id}&cohort=all")->assertInertia(fn (Assert $p) => $p
        ->where('comparison.b.n', 5)
        ->where('comparison.moves', null));

    // The trend chart includes both final periods.
    $this->get('/')->assertInertia(fn (Assert $p) => $p
        ->where('kpis.previous_average', fn ($v) => $v !== null)
        ->loadDeferredProps(fn (Assert $r) => $r->where('charts.trend.labels', ['2025/26 S2', '2026/27 S1'])));
});

it('shows a student history with level changes, narrative, PDF links and the latest level in the list', function () {
    [$prev] = app(TermService::class)->createTerm(AcademicYear::create(['name' => '2025/2026']), 2, null);
    $old = Classroom::create(['term_id' => $prev->id, 'grade' => 4, 'name' => '4A']);
    $kevin = Student::where('nipd', '5001')->first();
    $e = Enrollment::create(['classroom_id' => $old->id, 'student_id' => $kevin->id, 'term_id' => $prev->id]);
    Score::create(['enrollment_id' => $e->id, 'listening' => 12, 'reading' => 13, 'writing' => 10, 'speaking' => 12]); // total 47 → Beginner
    app(FinalizationService::class)->finalize($old);

    $c5a = Classroom::where(['term_id' => $this->term->id, 'name' => '5A'])->first();
    Enrollment::where('classroom_id', $c5a->id)->whereHas('student', fn ($q) => $q->whereIn('nipd', ['5006', '5007']))->get()
        ->each(fn ($e) => Score::create(['enrollment_id' => $e->id, 'listening' => 12, 'reading' => 12, 'writing' => 12, 'speaking' => 12]));
    app(FinalizationService::class)->finalize($c5a);

    $this->get("/siswa/{$kevin->id}")->assertInertia(fn (Assert $p) => $p
        ->has('points', 2)
        ->where('points.0.overall_level', 'Beginner')
        ->where('points.0.total', 47)
        ->where('points.1.overall_level', 'Intermediate')
        ->where('points.1.change', 'up')
        ->where('points.1.pdf_url', fn ($u) => str_contains($u, '/pdf'))
        ->where('latestLevel', 'Intermediate')
        ->where('narrative', fn ($n) => str_starts_with($n, 'Kevin naik dari Beginner (Kelas 4, Semester 2) ke Intermediate (Kelas 5, Semester 1). Skill dengan kenaikan terbesar: Reading (+4 poin).')));

    $this->get('/siswa?q=kevin')->assertInertia(fn (Assert $p) => $p->where('students.data.0.last_level', 'Intermediate'));
});

it('shows draft points in the history as "draft"', function () {
    $kevin = Student::where('nipd', '5001')->first();

    $this->get("/siswa/{$kevin->id}")->assertInertia(fn (Assert $p) => $p
        ->has('points', 1)
        ->where('points.0.source', 'draft')
        ->where('points.0.pdf_url', null)
        ->where('narrative', null)
        ->where('latestLevel', null));
});
