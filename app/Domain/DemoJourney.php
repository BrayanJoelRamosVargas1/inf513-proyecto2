<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class DemoJourney
{
    public function run(): int
    {
        abort_unless(app()->environment('local') && config('portal.experience') === 'training', 403);
        $title = 'Recorrido · Evaluación y desempate DEMO';
        $existing = DB::table('calls')->whereIn('title', [$title, 'Recorrido probado · Evaluación y desempate DEMO'])->first();
        if ($existing) {
            // A previous scan failure can leave a legitimate, incomplete workflow.
            // Its existence is not evidence that evaluation and publication finished.
            $offers = DB::table('offers')->where('call_id', $existing->id);
            $completed = $existing->mode === 'DEMO' && (clone $offers)->count() === 1
                && (clone $offers)->where('needs_publication_review', false)
                    ->join('publications', 'publications.offer_id', '=', 'offers.id')
                    ->join('tie_exams', 'tie_exams.offer_id', '=', 'offers.id')
                    ->whereColumn('publications.result_revision', 'offers.result_revision')
                    ->whereColumn('tie_exams.result_revision', 'offers.result_revision')
                    ->where('tie_exams.status', 'resolved')->exists();
            if (! $completed) {
                Scores::fail('Existe un recorrido DEMO anterior incompleto o en revisión. Su historial se conserva: revise el fallo antes de continuar; no se declara ejecutado.');
            }

            return $existing->id;
        }
        $s = app(Selection::class);
        $authority = User::where('email', 'autoridad@ficct.test')->firstOrFail();
        $reviewer = User::where('email', 'revisor@ficct.test')->firstOrFail();
        $teacher = User::where('email', 'docente@ficct.test')->firstOrFail();
        $call = $s->createCall($authority, ['title' => $title, 'opens_at' => now()->subDays(2), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM', 'source' => 'Fixture de recorrido acelerado DEMO. Datos ficticios y estados avanzados por autoridad de prueba; no es un concurso real.']);
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
                $document = $s->upload($student, $id, $kind, new UploadedFile(resource_path('demo-documents/'.$kind.'.pdf'), 'MUESTRA-'.$kind.'.pdf', 'application/pdf', null, true));
                $task = app(DocumentProcessor::class)->enqueue($document, 'applicant');
                app(DocumentProcessor::class)->runOne($task);
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
        $s->resolveTie($authority, $tie, [$apps[0] => 'Nuevo examen DEMO: 85/100 según acta ficticia', $apps[1] => 'Nuevo examen DEMO: 80/100 según acta ficticia'], [$apps[0]], 'ACTA FICTICIA: comisión de demostración decide tras nuevo examen exclusivo; sin efectos institucionales.');
        $s->approve($authority, $offer, 'APROBACIÓN DEMO del recorrido con revisión, notas y nuevo examen; no es un nombramiento.');

        return $call;
    }
}
