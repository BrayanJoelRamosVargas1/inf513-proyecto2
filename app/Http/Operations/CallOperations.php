<?php

namespace App\Http\Operations;

use App\Domain\Scores;
use App\Domain\Selection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Convocatorias, ofertas y asignación de evaluadores.
 * Valida la petición y ejecuta la regla de dominio; no decide la respuesta HTTP.
 * Lo usan por igual los controladores Blade (redirección) y la API (JSON).
 */
final class CallOperations
{
    public function __construct(private Selection $service) {}

    public function createCall(Request $r): int
    {
        $d = $r->validate(['title' => 'required|string|max:150', 'opens_at' => 'required|date', 'closes_at' => 'required|date', 'exam_at' => 'required|date', 'scope' => ['required', Rule::in(['UAGRM', 'faculty', 'career'])], 'scope_value' => 'nullable|required_unless:scope,UAGRM|string|max:80', 'subject_limit' => 'nullable|integer|min:1|max:100', 'source' => 'required|string|max:2000', 'parent_id' => 'nullable|exists:calls,id']);

        $d += $r->validate(['management_year' => 'nullable|integer|digits:4', 'call_number' => 'nullable|string|max:40']);

        return $this->service->createCall($r->user(), $d);
    }

    public function transition(Request $r, int $id): void
    {
        $d = $r->validate(['status' => 'required|string']);
        $this->service->transition($r->user(), $id, $d['status']);
    }

    public function editCall(Request $r, int $id): void
    {
        $d = $r->validate(['title' => 'required|string|max:150', 'opens_at' => 'required|date', 'closes_at' => 'required|date', 'exam_at' => 'required|date', 'scope' => ['required', Rule::in(['UAGRM', 'faculty', 'career'])], 'scope_value' => 'nullable|required_unless:scope,UAGRM|string|max:80', 'subject_limit' => 'nullable|integer|min:1|max:100', 'source' => 'required|string|max:2000']);
        $d += $r->validate(['management_year' => 'nullable|integer|digits:4', 'call_number' => 'nullable|string|max:40']);
        $this->service->editCall($r->user(), $id, $d);
    }

    public function saveOffer(Request $r, int $id): void
    {
        $d = $r->validate(['title' => 'required|string|max:150', 'category_id' => 'required|integer|exists:categories,id', 'seats' => 'required|integer|min:0|max:999', 'modality' => 'required|string|max:40', 'is_subject' => 'nullable|boolean', 'requirement_profile' => ['nullable', Rule::in(['asignatura','sala','laboratorio'])], 'documents' => 'required|string|max:8000', 'requirements' => 'required|string|max:15000', 'weights' => 'required|string|max:3000', 'scheme' => ['required', Rule::in(['DEMO_AREAS_100', 'DEMO_COMPONENTS_100', 'PENDING_COMPOSITION'])], 'source' => 'required|string|max:2000']);
        foreach (['documents', 'requirements', 'weights'] as $key) {
            $d[$key] = $this->pairs($d[$key]);
        }
        if (! array_key_exists('cpd', $d['documents'])) {
            Scores::fail('Incluya el documento cpd=Formulario CPD.');
        }
        foreach (['documents', 'requirements'] as $field) {
            foreach (array_keys($d[$field]) as $key) {
                if (! preg_match('/^[a-z0-9_]{1,50}$/D', $key)) {
                    Scores::fail('Claves: minúsculas, números y guion bajo.');
                }
            }
        }
        $d['is_subject'] = $r->boolean('is_subject');
        $r->validate(['offer_id' => 'nullable|integer']);
        $this->service->saveOffer($r->user(), $id, $d, $r->filled('offer_id') ? (int) $r->input('offer_id') : null);
    }

    public function assign(Request $r, int $id): void
    {
        $d = $r->validate(['user_id' => 'required|integer', 'area' => 'required|string']);
        $this->service->assign($r->user(), $id, (int) $d['user_id'], $d['area']);
    }

    private function pairs(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', trim($text)) as $line) {
            $parts = explode('=', trim($line), 2);
            if (count($parts) !== 2 || ! trim($parts[0]) || ! trim($parts[1])) {
                Scores::fail('Use una fila clave=valor por elemento.');
            }$key = trim($parts[0]);
            if (array_key_exists($key, $out)) {
                Scores::fail('Hay claves duplicadas.');
            }$out[$key] = trim($parts[1]);
        }

        return $out;
    }
}
