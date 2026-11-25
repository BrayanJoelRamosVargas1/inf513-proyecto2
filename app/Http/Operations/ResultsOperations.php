<?php

namespace App\Http\Operations;

use App\Domain\Rectifications;
use App\Domain\Selection;
use Illuminate\Http\Request;

/**
 * Desempates, aprobación de resultados y rectificaciones de notas.
 * Valida la petición y ejecuta la regla de dominio; no decide la respuesta HTTP.
 * Lo usan por igual los controladores Blade (redirección) y la API (JSON).
 */
final class ResultsOperations
{
    public function __construct(private Selection $service) {}

    public function openTie(Request $r, int $id): void
    {
        $d = $r->validate(['commission' => 'required|string|max:1500', 'conditions' => 'required|string|min:20|max:5000', 'scheduled_at' => 'required|date']);
        $d['commission'] = array_values(array_filter(array_map('trim', explode(',', $d['commission']))));
        $this->service->openTie($r->user(), $id, $d);
    }

    public function resolveTie(Request $r, int $id): void
    {
        $d = $r->validate(['results' => 'required|array', 'results.*' => 'required|string|max:1000', 'winners' => 'required|array', 'winners.*' => 'required|integer', 'minutes' => 'required|string|min:20|max:8000']);
        $this->service->resolveTie($r->user(), $id, $d['results'], array_map('intval', $d['winners']), $d['minutes']);
    }

    public function approve(Request $r, int $id): void
    {
        $d = $r->validate(['minutes' => 'required|string|min:20|max:8000']);
        $this->service->approve($r->user(), $id, $d['minutes']);
    }

    public function continueTie(Request $r, int $id): void
    {
        $service = app(Selection::class);
        $d = $r->validate(['results' => 'required|array', 'results.*' => 'required|string|max:1000', 'minutes' => 'required|string|min:20|max:8000', 'commission' => 'required|string|max:1500', 'conditions' => 'required|string|min:20|max:5000', 'scheduled_at' => 'required|date']);
        $d['commission'] = array_values(array_filter(array_map('trim', explode(',', $d['commission']))));
        $service->continueTie($r->user(), $id, $d['results'], $d);
    }

    public function requestCorrection(Request $r, int $id): void
    {
        $service = app(Rectifications::class);
        $d = $r->validate(['grade_id' => 'nullable|integer', 'score' => 'required|string', 'reason' => 'required|string|min:10|max:3000', 'evidence' => 'required|string|min:10|max:3000']);
        $service->request($r->user(), $id, isset($d['grade_id']) ? (int) $d['grade_id'] : null, $d['score'], $d['reason'], $d['evidence']);
    }

    public function decideCorrection(Request $r, int $id): void
    {
        $service = app(Rectifications::class);
        $d = $r->validate(['approved' => 'required|boolean', 'reason' => 'required|string|min:10|max:3000']);
        $service->decide($r->user(), $id, (bool) $d['approved'], $d['reason']);
    }
}
