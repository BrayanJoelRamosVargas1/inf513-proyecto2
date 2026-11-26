<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Append-only descriptions of authentic evidence; never grants an academic permission. */
final class InstitutionalRegister
{
    public const KINDS = [
        'commission' => 'Comisión evaluadora',
        'act' => 'Acto administrativo',
        'template' => 'Plantilla institucional',
        'exam_plan' => 'Plan de examen',
        'calendar' => 'Calendario por modalidad',
    ];

    public const STATES = [
        'draft' => 'Borrador',
        'internal' => 'Registro interno',
        'documented' => 'Documento respaldado',
        'external_approval_recorded' => 'Aprobación externa registrada, sujeta a verificación',
    ];

    /** Neutraliza la inyección de fórmulas: una celda que empiece por = + - @ tabulador o retorno de carro se antepone con comilla. */
    public static function csvCell(mixed $value): mixed
    {
        return preg_match('/^[=+@\-\t\r]/', (string) $value) ? "'".$value : $value;
    }

    public const CALENDAR_FIELDS = [
        'opening' => 'Apertura propuesta',
        'closing' => 'Cierre propuesto',
        'exam_start' => 'Inicio de exámenes',
        'exam_end' => 'Fin de exámenes',
        'results_planned' => 'Publicación prevista',
        'results_actual' => 'Publicación efectiva documentada',
        'timetable_assignment' => 'Asignación de horarios',
    ];

    public function record(User $user, int $call, array $input): int
    {
        return Access::transaction($user, function () use ($user, $call, $input) {
            Selection::row('calls', $call, true);
            $kind = $input['kind'] ?? '';
            Access::require($user, $call, $kind === 'cpd' ? ['reviewer'] : ['authority']);
            $d = Validator::make($input, [
                'kind' => ['required', Rule::in(['commission', 'act', 'template', 'exam_plan', 'calendar', 'cpd'])],
                'reference' => 'required|string|max:100', 'expected_version' => 'required|integer|min:0',
                'source_id' => 'nullable|integer', 'payload' => 'required|array',
                'payload.modality' => ['required', Rule::in(['asignatura', 'sala', 'laboratorio'])],
                'payload.notes' => 'required|string|min:10|max:3000',
            ])->validate();
            $rules = match ($kind) {
                'commission' => [
                    'authority' => 'required|string|max:180',
                    'offer_id' => 'nullable|integer',
                    'issued_on' => 'required|date_format:Y-m-d',
                    'valid_until' => 'nullable|date_format:Y-m-d|after_or_equal:issued_on',
                    'members' => 'required|array|min:1|max:30',
                    'members.*.name' => 'required|string|max:180',
                    'members.*.responsibility' => 'required|string|max:180',
                    'observers' => 'nullable|string|max:1500',
                    'accreditation_on' => 'nullable|date_format:Y-m-d',
                ],
                'act' => [
                    'act_type' => [
                        'required',
                        Rule::in([
                            'informe',
                            'acta',
                            'aprobacion',
                            'desempate',
                            'publicacion',
                            'resolucion',
                            'designacion',
                            'desierta',
                            'vacancia',
                            'renuncia',
                            'excepcion',
                        ]),
                    ],
                    'authority' => 'required|string|max:180',
                    'issued_on' => 'required|date_format:Y-m-d',
                    'deadline_on' => 'nullable|date_format:Y-m-d',
                    'offer_id' => 'nullable|integer',
                ],
                'template' => [
                    'title' => 'required|string|max:180',
                    'issuer' => 'required|string|max:180',
                    'valid_from' => 'nullable|date_format:Y-m-d',
                    'valid_until' => 'nullable|date_format:Y-m-d'.(! empty($input['payload']['valid_from']) ? '|after_or_equal:valid_from' : ''),
                    'fields' => 'required|array|max:60',
                    'fields.*' => 'required|string|max:120',
                ],
                'exam_plan' => [
                    'commission_reference' => 'required|string|max:100',
                    'starts_at' => 'required|date',
                    'ends_at' => 'required|date|after_or_equal:starts_at',
                    'conditions' => 'required|string|min:10|max:3000',
                    'criteria' => 'required|array|min:1|max:20',
                    'criteria.*' => 'required|string|max:180',
                ],
                'calendar' => [
                    'timezone' => ['required', Rule::in(['America/La_Paz'])],
                    'opening' => 'nullable|date',
                    'closing' => 'nullable|date'.(! empty($input['payload']['opening']) ? '|after_or_equal:opening' : ''),
                    'exam_start' => 'nullable|date',
                    'exam_end' => 'nullable|date'.(! empty($input['payload']['exam_start']) ? '|after_or_equal:exam_start' : ''),
                    'results_planned' => 'nullable|date',
                    'results_actual' => 'nullable|date',
                    'timetable_assignment' => 'nullable|date',
                ],
                'cpd' => [
                    'application_id' => 'required|integer',
                    'document_id' => 'required|integer',
                    'issued_on' => 'required|date_format:Y-m-d',
                    'score' => 'required|numeric|decimal:0,2|min:0|max:40',
                    'components' => 'required|array',
                    'difference_reason' => 'nullable|string|min:10|max:3000',
                ],
            };
            $payload = Validator::make($input['payload'], $rules + ['modality' => [
                'required',
                Rule::in(['asignatura', 'sala', 'laboratorio']),
            ], 'notes' => 'required|string|min:10|max:3000', 'record_state' => [
                'nullable',
                Rule::in(['draft', 'internal', 'documented', 'external_approval_recorded']),
            ], 'approval_reference' => 'nullable|string|max:100', 'approval_authority' => 'nullable|string|max:180'])->validate();
            if (isset($payload['offer_id'])) {
                abort_unless(DB::table('offers')->where('id', $payload['offer_id'])->where('call_id', $call)->exists(), 422, 'La oferta no pertenece a esta convocatoria.');
            }
            $scope = $kind === 'cpd' ? 'application:'.(int) $payload['application_id'] : 'call';
            $latest = (int) DB::table('institutional_register')->where('call_id', $call)->where('kind', $kind)->where('record_scope', $scope)->where('reference', $d['reference'])->max('version');
            abort_unless($latest === (int) $d['expected_version'], 409, 'La referencia cambió. Consulte la última versión.');
            $sourceId = $d['source_id'] ?? null;
            $payload['record_state'] = $payload['record_state'] ?? ($sourceId ? 'documented' : 'internal');
            abort_if(in_array($payload['record_state'], ['documented', 'external_approval_recorded']) && ! $sourceId, 422, 'Este estado necesita un respaldo original verificable.');
            abort_if($payload['record_state'] === 'external_approval_recorded' && (empty($payload['approval_reference']) || empty($payload['approval_authority'])), 422, 'Identifique la aprobación externa y su autoridad emisora. Su registro no activa concursos oficiales.');
            if ($sourceId) {
                $source = Selection::row('institutional_sources', $sourceId, true);
                $dossier = app(InstitutionalPreparation::class)->dossier($user, $source->dossier_id);
                abort_unless($dossier->call_id === $call, 422, 'La fuente pertenece a otra convocatoria.');
                app(DocumentSafety::class)->assertCurrentSources($dossier->id);
                $payload['source_sha256'] = $source->sha256;
                $payload['source_version'] = $source->version;
            }
            if ($kind === 'cpd') {
                $application = Selection::row('applications', $payload['application_id'], true);
                $offer = Selection::row('offers', $application->offer_id);
                abort_unless($offer->call_id === $call && $application->status === 'submitted', 422);
                abort_unless(app(InstitutionalCatalogue::class)->modality($offer) === $payload['modality'], 422, 'La rúbrica no corresponde a la modalidad del expediente.');
                abort_if($application->user_id === $user->id, 403, 'No puede verificar su propio mérito.');
                $document = Selection::row('documents', $payload['document_id']);
                $effective = app(ApplicantDocuments::class)->effective($application->id);
                abort_unless($document->application_id === $application->id && $document->kind === 'cpd' && in_array($document->id, array_column($effective, 'id'), true), 422, 'Se requiere el CPD de la versión vigente de este expediente.');
                app(ApplicantDocuments::class)->assertClean([$document->id]);
                $definition = app(InstitutionalCatalogue::class)->definitions()['profiles']['convocatoria-01']['modalities'][$payload['modality']]['merit_components'];
                $components = [];
                foreach ($definition as $component) {
                    $components[$component['key']] = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$component['maximum']];
                }
                $payload['components'] = Validator::make($payload['components'], $components)->validate();
                $sum = '0.00';
                foreach ($payload['components'] as $score) {
                    $sum = bcadd($sum, (string) $score, 2);
                }
                abort_if(bccomp($sum, (string) $payload['score'], 2) !== 0 && empty($payload['difference_reason']), 422, 'La suma difiere del formulario: registre un fundamento sin sustituir el puntaje CPD.');
                $payload['component_sum'] = $sum;
                $payload['document_sha256'] = $document->sha256;
                $payload['document_version'] = $document->version;
            }
            $payload['validation'] = 'Registro documental pendiente de homologación; no es aprobación institucional.';
            $id = DB::table('institutional_register')->insertGetId([
                'call_id' => $call,
                'kind' => $kind,
                'record_scope' => $scope,
                'reference' => $d['reference'],
                'version' => $latest + 1,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'source_id' => $sourceId,
                'recorded_by' => $user->id,
                'created_at' => now(),
            ]);
            Selection::audit($user, $call, 'institutional.record.created', 'institutional-register:'.$id, null, [
                'kind' => $kind,
                'reference' => $d['reference'],
                'version' => $latest + 1,
            ]);

            return $id;
        });
    }
}
