<?php

namespace Database\Seeders;

use App\Domain\Selection;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('calls')->exists()) {
            $this->command?->info('Hay datos existentes: no se sobrescribió la DEMO.');

            return;
        }
        File::ensureDirectoryExists(base_path('.local'));
        $credentials = [];
        $users = [];
        foreach (['admin' => ['Administración DEMO', 'admin'], 'autoridad' => ['Vicedecanato DEMO', 'authority'], 'revisor' => ['Revisión DEMO', 'staff'], 'docente' => ['Docente DEMO', 'staff'], 'estudiante' => ['Andrea Ejemplo', 'student'], 'bruno' => ['Bruno Ejemplo', 'student'], 'carla' => ['Carla Ejemplo', 'student']] as $key => [$name,$role]) {
            $password = Str::random(22);
            $u = new User;
            $u->forceFill(['name' => $name, 'email' => $key.'@ficct.test', 'password' => Hash::make($password), 'role' => $role, 'faculty' => 'FICCT', 'career' => 'Sistemas'])->save();
            $users[$key] = $u;
            $credentials[] = $u->email.' | '.$password;
        }
        File::put(base_path('.local/ACCESOS_DEMO.txt'), "CUENTAS LOCALES FICTICIAS. NO PUBLICAR.\n".implode("\n", $credentials)."\n");
        foreach (['Docencia por asignatura' => 'docencia', 'Sala de cómputo' => 'docencia', 'Redes' => 'docencia', 'Software' => 'docencia', 'Electrónica' => 'docencia', 'Laboratorio académico/social' => 'laboratorio'] as $name => $regime) {
            DB::table('categories')->insert(['name' => $name, 'regime' => $regime, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $cats = DB::table('categories')->pluck('id', 'name');
        $docs = ['carta' => 'Carta de solicitud', 'cpd' => 'Formulario CPD de méritos', 'ci' => 'Identidad ficticia', 'academico' => 'Histórico/avance académico', 'autonomia' => 'Certificado de autonomía'];
        foreach ([1, 2] as $n) {
            $call = DB::table('calls')->insertGetId(['title' => sprintf('%02d/2026 · Referencia histórica', $n), 'mode' => 'HISTORICAL', 'status' => 'archived', 'opens_at' => '2026-01-01 00:00:00-04', 'closes_at' => $n === 1 ? '2026-01-30 15:30:00-04' : '2026-02-12 18:00:00-04', 'exam_at' => $n === 1 ? '2026-01-03 08:00:00-04' : '2026-02-13 08:00:00-04', 'scope' => 'UAGRM', 'subject_limit' => 4, 'parent_id' => $n === 2 ? $previous : null, 'source' => 'Convocatoria FICCT 0'.$n.'/2026. Fuente histórica. '.($n === 1 ? 'CONFLICTO DOCUMENTAL: examen de sala 03–05/01 anterior al cierre 30/01. No se corrige.' : ''), 'source_snapshot' => json_encode(['source' => 'Convocatoria_FICCT_0'.$n.'_2026.pdf', 'historical' => true]), 'created_at' => now(), 'updated_at' => now()]);
            $previous = $call;
            $matrices = $n === 1 ? ['Sala de cómputo' => [20, ['Soporte' => '50', 'Redes' => '25', 'Software' => '25']], 'Redes' => [3, ['Soporte' => '25', 'Redes' => '75']], 'Software' => [6, ['Software' => '100']], 'Electrónica' => [3, ['Soporte' => '25', 'Electrónica' => '75']]] : ['Sala de cómputo' => [13, ['Soporte' => '50', 'Redes' => '25', 'Software' => '25']], 'Software' => [3, ['Software' => '100']]];
            $matrices['Laboratorio académico/social'] = [$n === 1 ? 2 : 1, ['Práctico' => '60', 'Teórico' => '40']];
            foreach ($matrices as $name => [$seats,$weights]) {
                DB::table('offers')->insert(['call_id' => $call, 'category_id' => $cats[$name], 'title' => $name, 'seats' => $seats, 'documents' => json_encode($docs + ['cv' => 'CV con respaldos']), 'requirements' => json_encode(['activo' => 'Verificar estudiante activo y condiciones por tipo conforme fuente']), 'weights' => json_encode($weights), 'scheme' => 'PENDING_COMPOSITION', 'source' => 'Convocatoria 0'.$n.'/2026 p.'.($name === 'Laboratorio académico/social' ? ($n === 1 ? '6' : '5') : ($n === 1 ? '5' : '4')).'. Condiciones y composición para uso oficial pendientes.', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $s = app(Selection::class);
        $authority = $users['autoridad'];
        $call = $s->createCall($authority, ['title' => 'Auxiliaturas · Convocatoria DEMO 2026', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(14), 'exam_at' => now()->addDays(15), 'scope' => 'UAGRM', 'subject_limit' => 4, 'source' => 'Convocatoria ficticia. Fixtures técnicos basados en fuentes 2026; vigencia y composición institucional no acreditadas.']);
        foreach (['revisor' => 'reviewer', 'docente' => 'evaluator'] as $key => $role) {
            DB::table('memberships')->insert(['call_id' => $call, 'user_id' => $users[$key]->id, 'role' => $role]);
        }
        $offers = [];
        foreach (['Sala de cómputo' => ['Soporte' => '50', 'Redes' => '25', 'Software' => '25'], 'Software' => ['Software' => '100'], 'Docencia por asignatura' => ['Escrito' => '60', 'Práctico' => '40'], 'Laboratorio académico/social' => ['Práctico' => '60', 'Teórico' => '40']] as $name => $weights) {
            $offers[$name] = $s->saveOffer($authority, $call, ['category_id' => $cats[$name], 'title' => $name === 'Docencia por asignatura' ? 'INF120 · Programación I (DEMO)' : $name, 'seats' => 2, 'is_subject' => $name === 'Docencia por asignatura', 'modality' => 'Presencial', 'documents' => $name === 'Docencia por asignatura' ? $docs : $docs + ['cv' => 'CV con respaldos'], 'requirements' => ['activo' => 'Estudiante activo: cotejo de evidencia', 'regimen' => 'Requisitos específicos de la modalidad: verificación manual documentada'], 'weights' => $weights, 'scheme' => $name === 'Sala de cómputo' || $name === 'Software' ? 'DEMO_AREAS_100' : 'DEMO_COMPONENTS_100', 'source' => 'Fixture explícito: escala 0..100, conversión proporcional a 60. HALF_UP al finalizar. No composición normativa entre áreas y componentes.']);
            foreach (array_keys($weights) as $area) {
                $s->assign($authority, $offers[$name], $users['docente']->id, $area);
            }
        }
        $s->transition($authority, $call, 'published');
        // Una postulación borrador permite comenzar la demo sin alterar un proceso evaluado.
        $s->apply($users['estudiante'], $offers['Sala de cómputo']);
        $this->command?->info('DEMO creada sin datos reales. Accesos aleatorios: .local/ACCESOS_DEMO.txt (archivo privado).');
    }
}
