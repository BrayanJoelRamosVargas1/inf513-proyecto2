<?php

use App\Domain\Participation;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.'.config('database.default').'.database') !== 'ficct_test') {
    throw new RuntimeException('Solo ficct_test.');
}
require __DIR__.'/concurrency_documents.php';
$s = app(Selection::class);
$p = app(Participation::class);
$d = json_decode(file_get_contents($argv[1]), true);
$mode = $argv[2];
try {
    if ($mode === 'setup') {
        $u = [];
        foreach (['authority', 'reviewer', 'teacher', 'student'] as $role) {
            $u[$role] = User::factory()->create(['role' => $role === 'authority' ? 'authority' : ($role === 'student' ? 'student' : 'staff')]);
        }
        $cat = DB::table('categories')->insertGetId(['name' => 'Procesos race '.bin2hex(random_bytes(5)), 'regime' => 'docencia', 'active' => true]);
        $calls = [];
        $offers = [];
        $apps = [];
        foreach ([0, 1] as $i) {
            $calls[$i] = $s->createCall($u['authority'], ['title' => 'Procesos race DEMO '.$i, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM']);
            foreach (['reviewer' => 'reviewer', 'teacher' => 'evaluator'] as $name => $role) {
                DB::table('memberships')->insert(['call_id' => $calls[$i], 'user_id' => $u[$name]->id, 'role' => $role]);
            }
            foreach (($i === 0 ? [0] : [1, 2]) as $j) {
                $offers[$j] = $s->saveOffer($u['authority'], $calls[$i], ['category_id' => $cat, 'title' => 'Proceso '.$j, 'seats' => 1, 'is_subject' => false, 'modality' => 'DEMO', 'documents' => ['cpd' => 'CPD'], 'requirements' => ['activo' => 'Activo'], 'weights' => ['Software' => '100'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Fixture aislado sintético']);
            }
            $s->transition($u['authority'], $calls[$i], 'published');
        }
        foreach ($offers as $j => $offer) {
            $apps[$j] = $s->apply($u['student'], $offer);
            ficctRaceDocument($u['student'], $apps[$j]);
            $s->submit($u['student'], $apps[$j]);
            $s->review($u['reviewer'], $apps[$j], ['doc:cpd' => $j === 0 ? 'observed' : 'meets', 'activo' => 'meets'], 'Evidencia sintética para carrera concurrente.');
            if ($j) {
                $s->eligibility($u['reviewer'], $apps[$j], true, 'Habilitación sintética de ensayo.');
                $s->merit($u['reviewer'], $apps[$j], '20.10', 'CPD-SINTETICO');
                $s->assign($u['authority'], $offer, $u['teacher']->id, 'Software');
            }
        }
        $m = $p->open($u['reviewer'], $apps[0], ['cpd'], now()->addHour()->toIso8601String(), 'Ventana sintética concurrente.', 'ACTA-SINTETICA-CONCURRENCIA');
        ficctRaceDocument($u['student'], $apps[0], $m);
        $s->transition($u['authority'], $calls[1], 'closed');
        $s->transition($u['authority'], $calls[1], 'evaluation');
        foreach ([1, 2] as $j) {
            $s->grade($u['teacher'], $apps[$j], DB::table('assignments')->where('offer_id', $offers[$j])->value('id'), '80');
            $s->calculate($u['authority'], $apps[$j]);
            $s->approve($u['authority'], $offers[$j], 'Acta sintética previa a ensayo concurrente.');
        }
        $request = $p->request($u['student'], $apps[1], 'renunciation', 'Renuncia sintética para ensayo.', 'ACTA-SINTETICA-RENUNCIA');
        $d = ['users' => array_map(fn ($a) => $a->id, $u), 'calls' => $calls, 'offers' => $offers, 'apps' => $apps, 'amendment' => $m, 'request' => $request, 'publication' => DB::table('publications')->where('offer_id', $offers[2])->value('id'), 'old_publication' => DB::table('publications')->where('offer_id', $offers[1])->value('snapshot')];
        file_put_contents($argv[1], json_encode($d));
        echo 'READY';
        exit;
    }
    $authority = User::findOrFail($d['users']['authority']);
    $student = User::findOrFail($d['users']['student']);
    $reviewer = User::findOrFail($d['users']['reviewer']);
    while (microtime(true) < ($d['at'] ?? 0)) {
        usleep(10000);
    }
    if ($mode === 'submit') {
        $p->submit($student, $d['amendment']);
    }
    if ($mode === 'review') {
        $p->review($reviewer, $d['amendment'], ['doc:cpd' => 'observed', 'activo' => 'meets'], 'La evidencia sigue observada en ensayo sintético.');
    }
    if ($mode === 'renounce') {
        $p->decide($authority, $d['request'], true, 'Aceptación técnica sintética de renuncia.');
    }
    if ($mode === 'choice') {
        $p->choose($student, $d['apps'][2], $d['publication'], $argv[3], 0, 'Manifestación sintética concurrente.', 'ACTA-SINTETICA-MANIFESTACION');
    }
    if ($mode === 'prepare_upload') {
        $d['next_amendment'] = $p->open($reviewer, $d['apps'][0], ['cpd'], now()->addHour()->toIso8601String(), 'Nueva ventana técnica de ensayo.', 'ACTA-SINTETICA-NUEVA-VENTANA');
        ficctRaceDocument($student, $d['apps'][0], $d['next_amendment']);
        file_put_contents($argv[1], json_encode($d));
        echo 'READY';
        exit;
    }
    if ($mode === 'upload') {
        ficctRaceDocument($student, $d['apps'][0], $d['next_amendment']);
    }
    if ($mode === 'seal') {
        $p->submit($student, $d['next_amendment']);
    }
    if ($mode === 'verify') {
        $snapshot = Selection::json(DB::table('dossier_snapshots')->where('application_id', $d['apps'][0])->orderByDesc('id')->value('snapshot'));
        $doc = DB::table('documents')->where('amendment_id', $d['next_amendment'])->orderByDesc('version')->first();
        $before = DB::table('publications')->where('offer_id', $d['offers'][1])->value('snapshot');
        $s->approve($authority, $d['offers'][1], 'Acta sintética conserva vacante sin reemplazo.');
        $latest = Selection::json(DB::table('publications')->where('offer_id', $d['offers'][1])->orderByDesc('version')->value('snapshot'));
        echo json_encode(['dossier_version' => DB::table('applications')->where('id', $d['apps'][0])->value('dossier_version'), 'sealed_document_is_latest_accepted' => $snapshot['documents'][0]['id'] === $doc->id, 'renunciation_decisions' => DB::table('participation_decisions')->where('participation_request_id', $d['request'])->count(), 'vacancy_events' => DB::table('vacancy_events')->where('participation_request_id', $d['request'])->count(), 'held_vacancies' => DB::table('offers')->where('id', $d['offers'][1])->value('held_vacancies'), 'old_publication_preserved' => $before === $d['old_publication'], 'no_replacement' => $latest['selected'] === [], 'award_choice_versions' => DB::table('award_choices')->where('application_id',$d['apps'][2])->count()]);
        exit;
    }
    echo 'ACCEPTED';
} catch (ValidationException|HttpException $e) {
    echo 'REJECTED';
    exit(2);
}
