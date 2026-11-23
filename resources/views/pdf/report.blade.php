@php
    /** @var array $r  built by App\Services\ReportDataBuilder */
    $paper = $r['paper'];
    $pageW = $paper === 'a4' ? 210 : 215;
    $pageH = $paper === 'a4' ? 297 : 330;
    $m = $r['layout']['margin_mm'];
    $s = $r['sections'];
    $p = $r['theme']['primary'];
    $a = $r['theme']['accent'];
    // F4 is ~33 mm taller: spend it on spacing. Switched-off blocks give their room back too.
    $off = (! $s['level_map']) + (! $s['radar']) + (! $s['can_do']) * 2;
    $tight = max(0, $m - 12); // wider margins leave less room: tighten spacing
    $gap = max(1.6, ($paper === 'f4' ? 4.6 : 3) + $off * 0.9 - $tight * 0.25);
    $sigSpace = max(8, ($paper === 'f4' ? 15 : 9) + $off * 1.5 - $tight * 0.6);
    // A4 is 33 mm shorter: slightly smaller type & padding. Pagella sets narrower than the old DejaVu, so both run larger.
    $k = $paper === 'f4' ? 1.12 : 1.04;
    $pdf = $pdf ?? true;
    // DomPDF reads the font from disk; the browser preview loads it over HTTP.
    $fontBase = $pdf ? str_replace('\\', '/', public_path('fonts/pagella')) : asset('fonts/pagella');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $r['student']['name'] }} — English Proficiency Progress Report</title>
<style>
    @font-face { font-family: 'Pagella'; font-weight: normal; font-style: normal; src: url('{{ $fontBase }}/TeXGyrePagella-regular.ttf') format('truetype'); }
    @font-face { font-family: 'Pagella'; font-weight: bold; font-style: normal; src: url('{{ $fontBase }}/TeXGyrePagella-bold.ttf') format('truetype'); }
    @page { margin: {{ $m }}mm; size: {{ $pageW }}mm {{ $pageH }}mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Pagella', 'Book Antiqua', Palatino, serif; font-size: {{ 7.4 * $k }}pt; color: #111827; line-height: 1.25; }
    @if (! $pdf)
        html { background: #e5e7eb; }
        body { padding: 0; }
        .sheet { width: {{ $pageW }}mm; min-height: {{ $pageH }}mm; padding: {{ $m }}mm; margin: 0 auto; background: #fff; position: relative; overflow: hidden; }
    @else
        .sheet { position: relative; }
    @endif
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; padding: 0; }
    .strip { height: 2mm; background: {{ $a }}; }
    .strip-bottom { height: 1.6mm; background: {{ $p }}; margin-top: {{ $gap * .7 }}mm; }
    .kop { background: {{ $p }}; color: #fff; }
    .kop td { vertical-align: middle; padding: {{ 2 * $k }}mm 2.5mm; }
    .logo { width: 19mm; text-align: center; }
    .logo img { max-width: {{ 16 * $k }}mm; max-height: {{ 16 * $k }}mm; }
    .kop .center { text-align: center; }
    .kop .extra { font-size: {{ 6.5 * $k }}pt; letter-spacing: .3pt; }
    .kop .school { font-weight: bold; font-size: {{ $r['fs']['school'] * .93 * $k }}pt; letter-spacing: .5pt; line-height: 1.15; }
    .kop .collab { font-size: {{ 7 * $k }}pt; margin-top: .4mm; }
    .kop .collab b { color: {{ $a }}; letter-spacing: .4pt; }
    .ng { width: 34mm; text-align: center; }
    .ng div { background: #fff; border-radius: 1.5mm; padding: 1mm 1.5mm; }
    .ng img { width: 100%; }
    .kop .title { font-size: {{ 10 * $k }}pt; font-weight: bold; letter-spacing: .7pt; margin-top: .8mm; }
    .kop .subtitle { font-size: {{ 6.5 * $k }}pt; }
    .block { margin-top: {{ $gap }}mm; }
    .h { font-size: {{ 6.8 * $k }}pt; font-weight: bold; color: {{ $p }}; letter-spacing: .5pt; border-bottom: .45mm solid {{ $a }}; padding-bottom: .4mm; margin-bottom: {{ 1 * $k }}mm; }
    .info td { padding: {{ .55 * $k }}mm 1.2mm; border-bottom: .2mm solid #e2e8f0; }
    .info .lbl { color: #4b5563; width: 26mm; font-size: {{ 6.8 * $k }}pt; }
    .info .val { font-weight: bold; font-size: {{ 7.8 * $k }}pt; }
    .info .val.name { font-size: {{ $r['fs']['name'] * .9 * $k }}pt; }
    .overall { text-align: center; border-radius: 2mm; padding: {{ 1.8 * $k }}mm 1.5mm; }
    .overall .cap { font-size: {{ 6.5 * $k }}pt; font-weight: bold; letter-spacing: .5pt; }
    .overall .code { font-size: {{ 20 * $k }}pt; font-weight: bold; line-height: 1.05; }
    .overall .lname { font-size: {{ 6.8 * $k }}pt; }
    .overall .score { font-size: {{ 7.8 * $k }}pt; font-weight: bold; margin-top: .5mm; }
    .map { table-layout: fixed; }
    .map td { text-align: center; padding: 0 .5mm; }
    .map .cam { font-size: {{ 5.6 * $k }}pt; color: #4b5563; vertical-align: bottom; padding-bottom: .5mm; }
    .map .box { border-radius: 1.3mm; padding: {{ 1.1 * $k }}mm .4mm; font-size: {{ 8.2 * $k }}pt; font-weight: bold; }
    .map .box small { display: block; font-size: {{ 5.6 * $k }}pt; font-weight: normal; }
    .map .box.dim { opacity: .45; }
    .map .box.cur { border: .55mm solid #111827; }
    .map .mark { font-size: {{ 5.6 * $k }}pt; font-weight: bold; padding-top: .6mm; }
    .tri { font-family: 'DejaVu Sans', sans-serif; } /* ▲ is not in Pagella */
    .map .subs { padding-top: .6mm; white-space: nowrap; }
    .map .sub { display: inline-block; font-size: {{ 5.6 * $k }}pt; color: #4b5563; padding: .2mm .7mm; border-radius: .8mm; border: .2mm solid transparent; }
    .map .sub.on { font-weight: bold; border-color: #111827; }
    .skills th, .cando th { font-size: {{ 6.4 * $k }}pt; color: #4b5563; text-align: left; padding: .6mm 1.2mm; border-bottom: .3mm solid #cbd5e1; }
    .skills td { padding: {{ 1.45 * $k }}mm 1.2mm; border-bottom: .2mm solid #e2e8f0; vertical-align: middle; font-size: {{ 7.6 * $k }}pt; }
    .skills .total td { font-weight: bold; border-top: .4mm solid {{ $p }}; border-bottom: none; }
    .badge { display: inline-block; border-radius: 3mm; padding: .3mm 1.8mm; font-size: {{ 6.8 * $k }}pt; font-weight: bold; min-width: 9mm; text-align: center; }
    .cando td { padding: {{ .75 * $k }}mm 1.2mm; border-bottom: .2mm solid #e2e8f0; font-size: {{ 6.6 * $k }}pt; line-height: 1.22; }
    .cando .sk { width: 19mm; font-weight: bold; font-size: {{ 7.2 * $k }}pt; }
    .comment { border: .3mm solid #cbd5e1; border-left: 1.1mm solid {{ $a }}; border-radius: 1.3mm; padding: {{ 1.5 * $k }}mm 2.5mm; font-size: {{ $r['fs']['comment'] * .9 * $k }}pt; line-height: 1.35; }
    .sign td { text-align: center; font-size: {{ 6.8 * $k }}pt; padding: 0 1.2mm; }
    .sign .role { color: #4b5563; }
    .sign .space { height: {{ $sigSpace }}mm; }
    .sign .who { font-weight: bold; border-top: .3mm solid #111827; padding-top: .6mm; font-size: {{ 7.2 * $k }}pt; }
    .place { text-align: right; font-size: {{ 7.2 * $k }}pt; margin-bottom: 1mm; }
    .draft { position: absolute; top: {{ $pageH * .36 }}mm; left: 0; width: 100%; text-align: center; font-size: 95pt; font-weight: bold; color: rgba(180, 35, 24, .10); transform: rotate(-30deg); letter-spacing: 6mm; }
    .warn { background: #FFF4E5; color: #9A5B00; border: .3mm solid #9A5B00; padding: 1.5mm 2mm; border-radius: 1.5mm; margin-bottom: 2mm; font-size: 8pt; }
</style>
</head>
<body>
<div class="sheet">
    @foreach ($r['warnings'] as $w)
        <div class="warn">⚠ {{ $w }}</div>
    @endforeach

    @if ($r['draft'] && ! $r['sample'])
        <div class="draft">DRAFT</div>
    @endif

    <div class="strip"></div>

    {{-- 2. Kop --}}
    <table class="kop">
        <tr>
            <td class="logo">@if ($r['logos']['school'])<img src="{{ $r['logos']['school'] }}" alt="">@endif</td>
            <td class="center">
                @if ($r['school']['extra_line'])<div class="extra">{{ $r['school']['extra_line'] }}</div>@endif
                <div class="school">{{ $r['school']['name'] }}</div>
                <div class="collab">In Collaboration with <b>NATIONAL GEOGRAPHIC LEARNING</b></div>
                <div class="title">{{ $r['title'] }}</div>
                <div class="subtitle">{{ $r['subtitle'] }}</div>
            </td>
            <td class="ng"><div><img src="{{ $r['logos']['natgeo'] }}" alt="National Geographic Learning"></div></td>
        </tr>
    </table>

    {{-- 3. Student information --}}
    <div class="block">
        <div class="h">STUDENT INFORMATION</div>
        <table class="info">
            <tr>
                <td class="lbl">Student Name</td><td class="val name">{{ $r['student']['name'] }}</td>
                <td class="lbl">NIPD</td><td class="val">{{ $r['student']['nipd'] }}</td>
            </tr>
            <tr>
                <td class="lbl">Class / Grade</td><td class="val">{{ $r['student']['class'] }}  /  Grade {{ $r['student']['grade'] }}</td>
                <td class="lbl">NISN</td><td class="val">{{ $r['student']['nisn'] ?: '–' }}</td>
            </tr>
            <tr>
                <td class="lbl">Homeroom Teacher</td><td class="val">{{ $r['teachers']['homeroom'] ?: '–' }}</td>
                <td class="lbl">Semester</td><td class="val">{{ $r['term']['label'] }}  •  {{ $r['term']['year'] }}</td>
            </tr>
        </table>
    </div>

    {{-- 4–5. Grade 6 only: National Geographic level card + CEFR level map. The teacher total is in the skills table. --}}
    @if ($s['level_map'])
    <div class="block">
        <table>
            <tr>
                <td style="width: 36mm; padding-right: 3mm;">
                    @if ($c = $r['natgeo_card'])
                        <div class="overall" style="background: {{ $c['bg'] }}; color: {{ $c['fg'] }};">
                            <div class="cap">CEFR LEVEL</div>
                            <div class="code">{{ $c['level'] }}</div>
                            <div class="score">{{ $c['sub'] }} · {{ $c['name'] }}</div>
                        </div>
                    @else
                        <div class="overall" style="background: #e5e7eb; color: #4b5563;">
                            <div class="cap">CEFR LEVEL</div>
                            <div class="code">–</div>
                            <div class="score">Belum diisi</div>
                        </div>
                    @endif
                </td>
                    <td>
                        <div class="h">ONLINE PLACEMENT TEST ALIGNED WITH THE CEFR LEVEL MAP</div>
                        <table class="map">
                            @if ($s['cambridge'])
                                <tr>@foreach ($r['levels'] as $l)<td class="cam">{{ $l['cambridge'] }}</td>@endforeach</tr>
                            @endif
                            <tr>
                                @foreach ($r['levels'] as $l)
                                    <td>
                                        <div class="box {{ $l['current'] ? 'cur' : '' }}" style="background: {{ $l['bg'] }}; color: {{ $l['fg'] }};">
                                            {{ $l['level'] }}<small>{{ $l['short_name'] }}</small>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                            {{-- National Geographic sub-levels under each category; the student's one is marked --}}
                            <tr>
                                @foreach ($r['levels'] as $l)
                                    <td class="subs">
                                        @foreach ($l['subs'] as $sub)
                                            <span class="sub {{ $sub['current'] ? 'on' : '' }}" @if ($sub['current']) style="background: {{ $l['bg'] }}; color: {{ $l['fg'] }};" @endif>{{ $sub['label'] }}</span>
                                        @endforeach
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                @foreach ($r['levels'] as $l)
                                    <td class="mark">@if ($l['current'])<span class="tri">▲</span> CURRENT LEVEL: {{ $r['natgeo'] }} @endif</td>
                                @endforeach
                            </tr>
                        </table>
                    </td>
            </tr>
        </table>
    </div>
    @endif

    {{-- 6–7. Skill assessment + line chart --}}
    <div class="block">
        <table>
            <tr>
                <td style="padding-right: {{ $r['chart'] ? '4mm' : '0' }}; {{ $r['chart'] ? 'width: 50%;' : '' }}">
                    <div class="h">SKILL ASSESSMENT BY TEACHER</div>
                    <table class="skills">
                        <tr>
                            <th>Skill</th><th style="text-align:right">Score</th>
                        </tr>
                        @foreach ($r['skills'] as $sk)
                            <tr>
                                <td style="font-weight:bold">{{ $sk['label'] }}</td>
                                <td style="text-align:right">{{ $sk['score'] }} / {{ $sk['max'] }}</td>
                            </tr>
                        @endforeach
                        <tr class="total">
                            <td>TOTAL</td><td style="text-align:right">{{ $r['overall']['total'] }} / {{ $r['overall']['max'] }}</td>
                        </tr>
                    </table>
                </td>
                @if ($r['chart'])
                    {{-- Same width as the skills table; the chart is drawn 420×240 px (7:4). --}}
                    <td style="width: 50%; text-align: center;">
                        <div class="h">SKILL PROFILE</div>
                        <img src="{{ $r['chart'] }}" alt="Skill profile" style="width: {{ $paper === 'f4' ? 84 : 80 }}mm; height: {{ $paper === 'f4' ? 48 : 45.7 }}mm;">
                    </td>
                @endif
            </tr>
        </table>
    </div>

    {{-- 8. Can-do & next steps --}}
    @if ($s['can_do'])
        <div class="block">
            <div class="h">WHAT THE STUDENT CAN DO  &amp;  NEXT STEPS</div>
            <table class="cando">
                <tr><th>Skill</th><th style="width:47%">What the student can do now</th><th>Next step to grow</th></tr>
                @foreach ($r['skills'] as $sk)
                    <tr>
                        <td class="sk">{{ $sk['label'] }}<br><span class="badge" style="margin-top:.6mm; background: {{ $sk['bg'] }}; color: {{ $sk['fg'] }};">{{ $sk['level'] }}</span></td>
                        <td>{{ $sk['can_do'] }}</td>
                        <td>{{ $sk['next_step'] }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    {{-- 9. Comment --}}
    <div class="block">
        <div class="h">TEACHER'S COMMENT</div>
        <div class="comment">{{ $r['comment'] }}</div>
    </div>

    {{-- 10. Signatures --}}
    <div class="block">
        <div class="place">{{ $r['place_date'] }}</div>
        <table class="sign">
            <tr>
                @foreach ($r['signatures'] as $sig)
                    <td style="width: {{ 100 / max(1, count($r['signatures'])) }}%">
                        <div class="role">{{ $sig['label'] }}</div>
                        <div class="space"></div>
                        <div class="who">{{ $sig['name'] ?: '(.................................)' }}</div>
                        @if ($sig['id_line'])<div class="role">{{ $sig['id_line'] }}</div>@endif
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

    <div class="strip-bottom"></div>
</div>
</body>
</html>
