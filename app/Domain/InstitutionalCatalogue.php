<?php

namespace App\Domain;

use Illuminate\Support\Facades\DB;

/** Documentary import, never an authorization to reopen or operate a competition. */
final class InstitutionalCatalogue
{
    public function __construct(private readonly ?string $directory = null) {}

    private function path(string $file): string
    {
        return ($this->directory ?? resource_path('institutional')).DIRECTORY_SEPARATOR.$file;
    }

    public function modality(object $offer): string
    {
        return $offer->is_subject ? 'asignatura' : (Selection::row('categories', $offer->category_id)->regime === 'laboratorio' ? 'laboratorio' : 'sala');
    }

    /** Se decodifica una sola vez por petición y archivo; la clave incluye fecha y tamaño para no servir un catálogo modificado. */
    private static array $definitionsCache = [];

    public function definitions(): array
    {
        $path = $this->path('catalogue.json');
        $key = $path.'|'.filemtime($path).'|'.filesize($path);

        return self::$definitionsCache[$key] ??= json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function original(string $key): array
    {
        $source = $this->definitions()['sources'][$key] ?? abort(404);
        $file = $this->path($source['file']);
        abort_unless(is_file($file), 404, 'Fuente original no disponible. No se ofrece un sustituto.');
        $bytes = file_get_contents($file);
        abort_unless(hash_equals($source['sha256'], hash('sha256', $bytes)), 409, 'La fuente no coincide con el original verificado.');
        abort_unless(str_starts_with($bytes, '%PDF-'), 415);

        return [$source, $bytes];
    }

    public function import(int $first, int $second): array
    {
        abort_if($first === $second, 422, 'Las convocatorias deben ser distintas.');
        $definitions = $this->definitions();
        $subjects = json_decode(file_get_contents($this->path('subjects.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach (array_keys($definitions['sources']) as $key) {
            $this->original($key);
        }
        abort_unless(count($subjects['convocatoria-01']) === 49 && count($subjects['convocatoria-02']) === 17, 409);
        return DB::transaction(function () use ($first, $second, $definitions, $subjects) {
            DB::select('SELECT pg_advisory_xact_lock(20261125, 1)');
            $category = DB::table('categories')->where('name', 'Docencia por asignatura')->value('id') ?? abort(422, 'Falta la categoría docencia por asignatura.');
            $added = 0;
            foreach ([$first, $second] as $index => $callId) {
                $key = 'convocatoria-0'.($index + 1);
                $call = Selection::row('calls', $callId, true);
                abort_unless($call->mode === 'HISTORICAL' && $call->status === 'archived', 422, 'Solo se importan referencias históricas archivadas.');
                abort_unless(str_contains($call->source ?? '', '0'.($index + 1).'/2026'), 409, 'La referencia existente no corresponde al número indicado.');
                abort_if(DB::table('applications')->whereIn('offer_id', DB::table('offers')->where('call_id', $callId)->select('id'))->exists(), 409, 'No se modifica un proceso con postulaciones.');
                $profile = $definitions['profiles'][$key];
                $source = $definitions['sources'][$key];
                $old = DB::table('institutional_call_profiles')->where('call_id', $callId)->first();
                if ($old) {
                    abort_unless($old->source_key === $key && hash_equals($old->source_sha256, $source['sha256']), 409);
                    abort_unless(Selection::json($old->profile) == $profile, 409, 'El perfil documental cambió; requiere una revisión versionada explícita.');
                } else {
                    DB::table('institutional_call_profiles')->insert(['call_id' => $callId, 'source_key' => $key, 'source_sha256' => $source['sha256'], 'profile' => json_encode($profile, JSON_UNESCAPED_UNICODE), 'imported_at' => now()]);
                }
                foreach ($subjects[$key] as $entry) {
                    $existing = DB::table('institutional_subjects')->where('code', $entry['sigla'])->first();
                    abort_if($existing && $existing->original_name !== $entry['nombre'], 409, 'Denominación divergente: '.$entry['sigla']);
                    $subjectId = $existing?->id ?? DB::table('institutional_subjects')->insertGetId(['code' => $entry['sigla'], 'original_name' => $entry['nombre']]);
                    if (DB::table('institutional_subject_offers')->where('call_id', $callId)->where('subject_id', $subjectId)->exists()) {
                        continue;
                    }
                    $title = $entry['sigla'].' · '.$entry['nombre'];
                    abort_if(DB::table('offers')->where('call_id', $callId)->where('title', $title)->exists(), 409, 'Existe una oferta sin enlace documental; requiere conciliación.');
                    $offer = DB::table('offers')->insertGetId(['call_id' => $callId, 'category_id' => $category, 'title' => $title, 'modality' => 'Presencial y/o virtual', 'seats' => null, 'is_subject' => true, 'requirement_profile' => 'asignatura',
                        'documents' => json_encode($profile['modalities']['asignatura']['documents'], JSON_UNESCAPED_UNICODE), 'requirements' => json_encode($profile['modalities']['asignatura']['requirements'], JSON_UNESCAPED_UNICODE),
                        'weights' => json_encode(['Escrito' => 60, 'Práctico' => 40]), 'scheme' => 'PENDING_COMPOSITION', 'source' => $source['name'].' p. '.$entry['pagina_pdf'].'; plazas, grupos y carga horaria no especificados.', 'created_at' => now(), 'updated_at' => now()]);
                    $n = $entry['numero_impreso'];
                    $group = $index === 0 ? match (true) { $n <= 29 => 'ÁREA COMÚN', $n <= 39 => 'ING. REDES Y TELECOMUNICACIONES', $n <= 45 => 'ING. INFORMÁTICA', $n <= 48 => 'ING. SISTEMAS', default => 'ING. ROBÓTICA' }
                        : match (true) { $n <= 9 => 'ÁREA COMÚN', $n <= 13 => 'ING. REDES Y TELECOMUNICACIONES', $n <= 15 => 'ING. INFORMÁTICA', default => 'ING. ROBÓTICA' };
                    DB::table('institutional_subject_offers')->insert(['offer_id' => $offer, 'subject_id' => $subjectId, 'call_id' => $callId, 'printed_number' => $n, 'source_page' => $entry['pagina_pdf'], 'source_group' => $group]);
                    $added++;
                }
            }
            return ['added_relations' => $added, 'subjects' => DB::table('institutional_subjects')->count(), 'relations' => DB::table('institutional_subject_offers')->count(), 'reopened_calls' => 0];
        });
    }

    public function forCall(int $id): ?array
    {
        $record = DB::table('institutional_call_profiles')->where('call_id', $id)->first();
        if (! $record) {
            return null;
        }
        $profile = Selection::json($record->profile);
        $profile['subjects'] = DB::table('institutional_subject_offers as relation')->join('institutional_subjects as subject', 'subject.id', '=', 'relation.subject_id')->where('relation.call_id', $id)->orderBy('relation.printed_number')->get(['relation.offer_id', 'relation.printed_number', 'relation.source_page', 'relation.source_group', 'subject.code', 'subject.original_name']);
        $profile['sources'] = collect($this->definitions()['sources'])->filter(fn ($v, $key) => in_array($key, ['resolucion', $record->source_key], true))->map(fn ($v, $key) => ['key' => $key, 'name' => $v['name'], 'pages' => $v['pages'], 'sha256' => $v['sha256']])->values();

        return $profile;
    }
}
