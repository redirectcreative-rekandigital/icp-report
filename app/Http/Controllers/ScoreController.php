<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Services\FinalizationService;
use App\Services\ScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Autosave + finalisation endpoints (PRD-01 §5, PRD-04 §4–6). */
class ScoreController extends Controller
{
    public function update(Request $request, Classroom $classroom, Enrollment $enrollment, ScoreService $scores): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', Rule::in(['listening', 'reading', 'writing', 'speaking', 'natgeo', 'comment'])],
            'value' => ['present', 'nullable'],
            'version' => ['required', 'integer', 'min:0'],
        ]);

        return response()->json($scores->update($classroom, $enrollment, $data['field'], $data['value'], (int) $data['version']));
    }

    /** Pre-check for the "Finalkan" dialog: who is still incomplete. */
    public function readiness(Classroom $classroom, FinalizationService $finalizations): JsonResponse
    {
        return response()->json(['incomplete' => $finalizations->incomplete($classroom)]);
    }

    public function finalize(Classroom $classroom, FinalizationService $finalizations): RedirectResponse
    {
        $finalizations->finalize($classroom);

        return redirect()->route('classes.report', $classroom)
            ->with('success', "Kelas {$classroom->name} berhasil difinalkan. Rapor siap dicetak.")
            ->with('finalized', true);
    }

    public function unfinalize(Request $request, Classroom $classroom, FinalizationService $finalizations): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:300'],
        ], [
            'reason.required' => 'Alasan wajib diisi.',
            'reason.min' => 'Alasan minimal 10 karakter.',
            'reason.max' => 'Alasan maksimal 300 karakter.',
        ]);

        $finalizations->unfinalize($classroom, trim($data['reason']));

        return redirect()->route('classes.scores', $classroom)
            ->with('success', "Finalisasi kelas {$classroom->name} dibatalkan. Nilai dapat diedit kembali.");
    }
}
