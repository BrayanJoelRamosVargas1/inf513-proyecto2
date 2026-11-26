<?php

namespace App\Http\Operations;

use App\Domain\Selection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Acciones sobre una postulación: archivo, envío, revisión, habilitación, méritos, notas y cálculo.
 * Valida la petición y ejecuta la regla de dominio; no decide la respuesta HTTP.
 * Lo usan por igual los controladores Blade (redirección) y la API (JSON).
 */
final class ApplicationOperations
{
    public function __construct(private Selection $service) {}

    public function action(Request $r, int $id, string $action): void
    {
        $u = $r->user();
        switch ($action) {
            case 'archivo':$d = $r->validate(['kind' => 'required|string|max:50', 'file' => 'required|file']);
                $this->service->upload($u, $id, $d['kind'], $d['file']);
                break;
            case 'enviar':$this->service->submit($u, $id);
                break;
            case 'revision':$d = $r->validate([
                'decisions' => 'required|array|min:1',
                'decisions.*' => 'required|string',
                'reason' => 'required|string|min:10|max:3000',
            ]);
                $this->service->review($u, $id, $d['decisions'], $d['reason']);
                break;
            case 'habilitacion':$d = $r->validate(['enabled' => 'required|boolean', 'reason' => 'required|string|min:10|max:3000']);
                $this->service->eligibility($u, $id, (bool) $d['enabled'], $d['reason']);
                break;
            case 'meritos':$d = $r->validate(['score' => 'required|string', 'reference' => 'required|string|max:150']);
                $this->service->merit($u, $id, $d['score'], $d['reference']);
                break;
            case 'nota':$d = $r->validate([
                'assignment_id' => 'required|integer',
                'score' => 'required|string',
                'intent' => ['nullable', Rule::in(['draft', 'confirm'])],
            ]);
                $this->service->grade($u, $id, (int) $d['assignment_id'], $d['score'], ($d['intent'] ?? 'confirm') === 'confirm');
                break;
            case 'calcular':$this->service->calculate($u, $id);
                break;
        }
    }
}
