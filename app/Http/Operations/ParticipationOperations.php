<?php

namespace App\Http\Operations;

use App\Domain\Participation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Subsanaciones, retiros, renuncias y manifestación sobre adjudicaciones.
 * Valida la petición y ejecuta la regla de dominio; no decide la respuesta HTTP.
 * Lo usan por igual los controladores Blade (redirección) y la API (JSON).
 */
final class ParticipationOperations
{
    public function open(Request $r, int $id): void
    {
        $s = app(Participation::class);
        $d = $r->validate(['kinds' => 'required|array|min:1', 'kinds.*' => 'required|string', 'deadline' => 'required|date', 'reason' => 'required|string|min:10|max:3000', 'evidence' => 'required|string|min:10|max:3000']);
        $s->open($r->user(), $id, $d['kinds'], $d['deadline'], $d['reason'], $d['evidence']);
    }

    public function amendment(Request $r, int $id, string $action): void
    {
        $s = app(Participation::class);
        if ($action === 'archivo') {
            $d = $r->validate(['kind' => 'required|string', 'file' => 'required|file']);
            $s->upload($r->user(), $id, $d['kind'], $d['file']);
        } elseif ($action === 'enviar') {
            $s->submit($r->user(), $id);
        } elseif ($action === 'revisar') {
            $d = $r->validate(['decisions' => 'required|array', 'decisions.*' => 'required|string', 'reason' => 'required|string|min:10|max:3000']);
            $s->review($r->user(), $id, $d['decisions'], $d['reason']);
        } else {
            $d = $r->validate(['reason' => 'required|string|min:10|max:3000']);
            $s->expire($r->user(), $id, $d['reason']);
        }
    }

    public function request(Request $r, int $id): void
    {
        $s = app(Participation::class);
        $d = $r->validate(['kind' => ['required', Rule::in(['withdrawal', 'renunciation'])], 'reason' => 'required|string|min:10|max:3000', 'evidence' => 'required|string|min:10|max:3000']);
        $s->request($r->user(), $id, $d['kind'], $d['reason'], $d['evidence']);
    }

    public function decide(Request $r, int $id): void
    {
        $s = app(Participation::class);
        $d = $r->validate(['approved' => 'required|boolean', 'reason' => 'required|string|min:10|max:3000']);
        $s->decide($r->user(), $id, (bool) $d['approved'], $d['reason']);
    }

    public function choose(Request $r, int $id): void
    {
        $s = app(Participation::class);
        $d = $r->validate(['publication_id' => 'required|integer', 'version' => 'required|integer|min:0', 'choice' => ['required', Rule::in(['accept', 'defer'])], 'reason' => 'required|string|min:10|max:3000', 'evidence' => 'required|string|min:10|max:3000']);
        $s->choose($r->user(), $id, (int) $d['publication_id'], $d['choice'], (int) $d['version'], $d['reason'], $d['evidence']);
    }
}
