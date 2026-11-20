<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Term;
use App\Services\ActivityLogger;
use App\Services\RecapExportService;
use App\Services\SpreadsheetExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Excel exports (PRD-03 §6). */
class ExportController extends Controller
{
    public function students(Term $term, SpreadsheetExportService $sheets): StreamedResponse
    {
        return $sheets->studentList($term);
    }

    public function classRecap(Classroom $classroom, RecapExportService $recaps, ActivityLogger $logger): StreamedResponse
    {
        $logger->log('export.class', 'data', "Rekap nilai kelas {$classroom->name} diekspor.", $classroom);

        return $recaps->classRecap($classroom);
    }

    public function termRecap(Term $term, RecapExportService $recaps, ActivityLogger $logger): StreamedResponse
    {
        $term->loadMissing('academicYear');
        $logger->log('export.term', 'data', "Rekap periode {$term->fullLabel()} diekspor.", $term);

        return $recaps->termRecap($term);
    }
}
