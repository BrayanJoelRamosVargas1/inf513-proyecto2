<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Additional synthetic scenarios; never replaces existing calls or documents. */
final class DocumentaryJourney
{
    public function run(): array
    {
        abort_unless(app()->environment('local') && config('portal.experience') === 'training', 403);
        $authority = User::where('email', 'autoridad@ficct.test')->firstOrFail();

        return Access::transaction($authority, function () use ($authority) {
            $result = [];
            $s = app(Selection::class);
            $reviewer = User::where('email', 'revisor@ficct.test')->firstOrFail();
            $evaluator = User::where('email', 'docente@ficct.test')->firstOrFail();
            foreach (['recepcion' => 'Práctica guiada · Postulación y revisión documental', 'evaluacion' => 'Práctica guiada · Evaluación con expediente PDF'] as $phase => $title) {
                if ($existing = DB::table('calls')->where('title', $title)->first()) {
                    $result[$phase] = $existing->id;

                    continue;
                }
                $call = $s->createCall($authority, ['title' => $title, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(14), 'exam_at' => now()->addDays(15), 'scope' => 'UAGRM', 'source' => 'Práctica sintética con documentos PDF A4 marcados MUESTRA. Calendario relativo de demostración; no representa una convocatoria institucional.']);
                foreach ([[$reviewer, 'reviewer'], [$evaluator, 'evaluator']] as [$u, $role]) {
                    DB::table('memberships')->insert(['call_id' => $call, 'user_id' => $u->id, 'role' => $role]);
                }
                $offer = $s->saveOffer($authority, $call, ['title' => 'Software · Práctica con documentos PDF', 'category_id' => DB::table('categories')->where('active', true)->where('name', 'Software')->firstOrFail()->id, 'seats' => 1, 'is_subject' => false, 'modality' => 'Presencial', 'documents' => ['carta' => 'Carta de solicitud', 'cpd' => 'Respaldo CPD', 'ci' => 'Identidad', 'academico' => 'Respaldo académico', 'autonomia' => 'Constancia requerida', 'cv' => 'Currículum y respaldos'], 'requirements' => ['activo' => 'Condición académica del escenario de práctica'], 'weights' => ['Software' => '100'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Escenario sintético: Software 100% sobre escala 0..100. Méritos 40 + examen 60. Los PDF no acreditan requisitos reales.']);
                $s->assign($authority, $offer, $evaluator->id, 'Software');
                $s->transition($authority, $call, 'published');
                $student = User::where('email', ($phase === 'recepcion' ? 'carla' : 'estudiante').'@ficct.test')->firstOrFail();
                $id = $s->apply($student, $offer);
                foreach (['carta', 'cpd', 'ci', 'academico', 'autonomia', 'cv'] as $kind) {
                    $document = $s->upload($student, $id, $kind, new UploadedFile(resource_path('demo-documents/'.$kind.'.pdf'), 'MUESTRA-'.$kind.'.pdf', 'application/pdf', null, true));
                    $task = app(DocumentProcessor::class)->enqueue($document, 'applicant');
                    app(DocumentProcessor::class)->runOne($task);
                }
                $s->submit($student, $id);
                if ($phase === 'recepcion') {
                    $bruno = User::where('email', 'bruno@ficct.test')->firstOrFail();
                    $s->apply($bruno, $offer);
                } else {
                    $s->review($reviewer, $id, array_fill_keys(['doc:carta', 'doc:cpd', 'doc:ci', 'doc:academico', 'doc:autonomia', 'doc:cv', 'activo'], 'meets'), 'Revisión de muestras sintéticas para practicar; no acredita requisitos reales.');
                    $s->eligibility($reviewer, $id, true, 'Habilitación exclusiva del escenario sintético de práctica.');
                    $s->merit($reviewer, $id, '20.00', 'CPD-MUESTRA-G09, sin emisión oficial');
                    $s->transition($authority, $call, 'closed');
                    $s->transition($authority, $call, 'evaluation');
                }
                $result[$phase] = $call;
            }

            return $result;
        });
    }
}
