<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class DemoJourney
{
    public function run(): int
    {
        abort_unless(app()->environment('local'), 403);
        $existing = DB::table('calls')->where('title', 'Recorrido probado · Evaluación y desempate DEMO')->first();
        if ($existing) {
            return $existing->id;
        }
        $s = app(Selection::class);
        $authority = User::where('email', 'autoridad@ficct.test')->firstOrFail();
        $reviewer = User::where('email', 'revisor@ficct.test')->firstOrFail();
        $teacher = User::where('email', 'docente@ficct.test')->firstOrFail();
        $call = $s->createCall($authority, ['title' => 'Recorrido probado · Evaluación y desempate DEMO', 'opens_at' => now()->subDays(2), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM', 'source' => 'Fixture de recorrido acelerado DEMO. Datos ficticios y estados avanzados por autoridad de prueba; no es un concurso real.']);
        foreach ([[$reviewer, 'reviewer'], [$teacher, 'evaluator']] as [$u,$role]) {
            DB::table('memberships')->insert(['call_id' => $call, 'user_id' => $u->id, 'role' => $role]);
        }
        $offer = $s->saveOffer($authority, $call, ['title' => 'Sala DEMO · 1 plaza con nuevo examen', 'category_id' => DB::table('categories')->where('name', 'Sala de cómputo')->value('id'), 'seats' => 1, 'is_subject' => false, 'modality' => 'Presencial', 'documents' => ['carta' => 'Carta ficticia', 'cpd' => 'CPD ficticio', 'ci' => 'Identidad ficticia', 'academico' => 'Avance académico ficticio', 'autonomia' => 'Constancia ficticia', 'cv' => 'CV ficticio'], 'requirements' => ['activo' => 'Estudiante activo (fixture)', 'regimen' => 'Requisitos específicos (fixture)'], 'weights' => ['Soporte' => '50', 'Redes' => '25', 'Software' => '25'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Fixture DEMO: notas sobre 100; 50/25/25; transformación a 60 y HALF_UP final.']);
        foreach (['Soporte', 'Redes', 'Software'] as $area) {
            $s->assign($authority, $offer, $teacher->id, $area);
        }
        $s->transition($authority, $call, 'published');
        $apps = [];
        foreach (['estudiante', 'bruno'] as $key) {
            $student = User::where('email', $key.'@ficct.test')->firstOrFail();
            $id = $s->apply($student, $offer);
            $apps[] = $id;
            foreach (['carta', 'cpd', 'ci', 'academico', 'autonomia', 'cv'] as $kind) {
                $path = tempnam(sys_get_temp_dir(), 'ficct-demo');
                $image = imagecreatetruecolor(640, 250);
                $bg = imagecolorallocate($image, 245, 248, 252);
                imagefill($image, 0, 0, $bg);
                $ink = imagecolorallocate($image, 25, 52, 81);
                imagestring($image, 5, 30, 40, 'FICCT - DOCUMENTO FICTICIO - SOLO DEMO', $ink);
                imagestring($image, 4, 30, 95, strtoupper($kind).' / '.$student->name, $ink);
                imagestring($image, 3, 30, 155, 'Sin validez institucional. No contiene datos reales.', $ink);
                imagepng($image, $path);
                imagedestroy($image);
                try {
                    $s->upload($student, $id, $kind, new UploadedFile($path, $kind.'.png', 'image/png', null, true));
                } finally {
                    unlink($path);
                }
            }
            $s->submit($student, $id);
            $criteria = array_fill_keys(['doc:carta', 'doc:cpd', 'doc:ci', 'doc:academico', 'doc:autonomia', 'doc:cv', 'activo', 'regimen'], 'meets');
            $s->review($reviewer, $id, $criteria, 'Cotejo de documentos sintéticos para el recorrido DEMO.');
            $s->eligibility($reviewer, $id, true, 'Se cumplen criterios del fixture DEMO; sin validación real.');
            $s->merit($reviewer, $id, '20.10', 'CPD-FICTICIO-'.now()->format('Ymd'));
        }
        $s->transition($authority, $call, 'closed');
        $s->transition($authority, $call, 'evaluation');
        foreach ($apps as $id) {
            foreach (DB::table('assignments')->where('offer_id', $offer)->get() as $as) {
                $s->grade($teacher, $id, $as->id, ['Soporte' => '80', 'Redes' => '60', 'Software' => '100'][$as->area]);
            }$s->calculate($authority, $id);
        }
        $tie = $s->openTie($authority, $offer, ['commission' => ['Integrante ficticio A', 'Integrante ficticio B'], 'conditions' => 'Condiciones sintéticas del nuevo examen: comisión conserva resultados descriptivos y registra decisión explícita. No se usa la nota original para elegir automáticamente.', 'scheduled_at' => now()->subMinute()]);
        $s->resolveTie($authority,$tie,[$apps[0] => 'Nuevo examen DEMO: 85/100 según acta ficticia', $apps[1] => 'Nuevo examen DEMO: 80/100 según acta ficticia'],[$apps[0]],'ACTA FICTICIA: comisión de demostración decide tras nuevo examen exclusivo; sin efectos institucionales.');
        $s->approve($authority,$offer,'APROBACIÓN DEMO del recorrido con revisión, notas y nuevo examen; no es un nombramiento.');

        return $call;
    }
}
