<?php

namespace Database\Seeders;

use App\Domain\InstitutionalCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Public historical sources only. Never provisions users, credentials or student dossiers. */
final class InstitutionalReferenceSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            abort_if(DB::table('calls')->exists() || DB::table('users')->exists(), 409, 'La instalación de referencia requiere una base nueva; no modifica datos existentes.');
            $catalogue = app(InstitutionalCatalogue::class);
            $definitions = $catalogue->definitions();
            $names = ['Docencia por asignatura', 'Sala de cómputo', 'Redes', 'Software', 'Electrónica', 'Laboratorio académico/social'];
            foreach ($names as $name) {
                DB::table('categories')->insertOrIgnore([
                    'name' => $name,
                    'regime' => $name === 'Laboratorio académico/social' ? 'laboratorio' : 'docencia',
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $calls = [];
            foreach ([1, 2] as $n) {
                $key = 'convocatoria-0'.$n;
                $profile = $definitions['profiles'][$key];
                $call = DB::table('calls')->insertGetId(['title' => '0'.$n.'/2026 · Referencia histórica', 'mode' => 'HISTORICAL', 'status' => 'archived', 'management_year' => 2026, 'call_number' => '0'.$n.'/2026',
                    'opens_at' => '2026-01-01 00:00:00-04:00', 'closes_at' => $profile['calendars']['asignatura']['closing'], 'exam_at' => $profile['calendars']['asignatura']['exam_start'].' 00:00:00-04:00',
                    'scope' => 'UAGRM', 'subject_limit' => 4, 'source' => 'Convocatoria FICCT 0'.$n.'/2026. Archivo documental, recepción cerrada.', 'source_snapshot' => json_encode([
                        'technical_placeholder_fields' => ['opens_at', 'exam_at'],
                        'official_calendar' => 'institutional_call_profiles',
                        'source_sha256' => $definitions['sources'][$key]['sha256'],
                    ]), 'created_at' => now(), 'updated_at' => now()]);
                $calls[] = $call;
                $matrices = $n === 1 ? [
                    'Sala de cómputo' => [20, ['Soporte' => 50, 'Redes' => 25, 'Software' => 25]],
                    'Redes' => [3, ['Soporte' => 25, 'Redes' => 75]],
                    'Software' => [6, ['Software' => 100]],
                    'Electrónica' => [3, ['Soporte' => 25, 'Electrónica' => 75]],
                ] : ['Sala de cómputo' => [13, ['Soporte' => 50, 'Redes' => 25, 'Software' => 25]], 'Software' => [3, ['Software' => 100]]];
                $matrices['Laboratorio académico/social'] = [$n === 1 ? 2 : 1, ['Práctico' => 60, 'Teórico' => 40]];
                foreach ($matrices as $name => [$seats, $weights]) {
                    $m = $profile['modalities'][$name === 'Laboratorio académico/social' ? 'laboratorio' : 'sala'];
                    DB::table('offers')->insert([
                        'call_id' => $call,
                        'category_id' => DB::table('categories')->where('name', $name)->value('id'),
                        'title' => $name,
                        'seats' => $seats,
                        'documents' => json_encode($m['documents']),
                        'requirements' => json_encode($m['requirements']),
                        'weights' => json_encode($weights),
                        'scheme' => 'PENDING_COMPOSITION',
                        'source' => $definitions['sources'][$key]['name'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $catalogue->import($calls[0], $calls[1]);
        });
    }
}
