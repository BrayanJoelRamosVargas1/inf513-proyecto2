<?php

namespace App\Http\Controllers\Api;

use App\Domain\Vacancies;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Plazas sin cubrir de una convocatoria y creación de la convocatoria siguiente con esas plazas. */
final class VacancyController extends Controller
{
    public function index(Request $r, int $id)
    {
        $report = app(Vacancies::class)->report($r->user(), $id);

        return response()->json(['call' => collect((array) $report['call'])->only(['id', 'title', 'status']), 'offers' => $report['offers'], 'max_consecutive' => Vacancies::MAX_CONSECUTIVE]);
    }

    public function carryOver(Request $r, int $id)
    {
        $d = $r->validate([
            'title' => 'required|string|max:150',
            'opens_at' => 'required|date',
            'closes_at' => 'required|date',
            'exam_at' => 'required|date',
            'source' => 'required|string|max:2000',
            'call_number' => 'nullable|string|max:40',
        ]);

        return response()->json(['id' => app(Vacancies::class)->carryOver($r->user(), $id, $d)], 201);
    }
}
