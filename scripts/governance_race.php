<?php

use App\Domain\Administration;
use App\Domain\Rectifications;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.'.config('database.default').'.database') !== 'ficct_test') {
    throw new RuntimeException('Solo ficct_test.');
}
$d = json_decode(file_get_contents($argv[1]), true);
$mode = $argv[2];
require __DIR__.'/concurrency_documents.php';
$s = app(Selection::class);
try {
    if ($mode === 'setup') {
        $users = [];
        foreach (['admin', 'authority', 'teacher', 'student'] as $role) {
            $users[$role] = User::factory()->create(['role' => $role === 'teacher' ? 'staff' : $role]);
        }
        $call = $s->createCall($users['authority'], ['title' => 'Concurrencia 0.2 sintética', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'scope' => 'UAGRM']);
        $cat = DB::table('categories')->insertGetId(['name' => 'Race 0.2 '.bin2hex(random_bytes(5)), 'regime' => 'docencia', 'active' => true]);
        $offers = [];
        $apps = [];
        $assignments = [];
        app(Administration::class)->grant($users['admin'], $call, $users['teacher']->id, 'evaluator', null, null, 'Permiso para ensayo sintético.');
        foreach ([0, 1] as $i) {
            $offers[$i] = $s->saveOffer($users['authority'], $call, ['category_id' => $cat, 'title' => 'Ensayo '.$i, 'modality' => 'DEMO', 'seats' => 1, 'is_subject' => false, 'documents' => ['cpd' => 'CPD'], 'requirements' => ['activo' => 'Activo'], 'weights' => ['Software' => '100'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Fixture aislado']);
            $s->assign($users['authority'], $offers[$i], $users['teacher']->id, 'Software');
            $assignments[$i] = DB::table('assignments')->where('offer_id', $offers[$i])->value('id');
        }
        $s->transition($users['authority'], $call, 'published');
        DB::table('memberships')->insert(['call_id' => $call, 'user_id' => $users['authority']->id, 'role' => 'reviewer']);
        foreach ($offers as $i => $offer) {
            $apps[$i] = $s->apply($users['student'], $offer);
            ficctRaceDocument($users['student'], $apps[$i]);
            $s->submit($users['student'], $apps[$i]);
            $s->review($users['authority'], $apps[$i], ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Cotejo de fixture sintético concurrente.');
            $s->eligibility($users['authority'], $apps[$i], true, 'Habilitación sintética para carrera.');
            $s->merit($users['authority'], $apps[$i], '20.10', 'CPD sintético concurrente');
        }
        $s->transition($users['authority'], $call, 'closed');
        $s->transition($users['authority'], $call, 'evaluation');
        $s->grade($users['teacher'], $apps[0], $assignments[0], '80');
        $s->calculate($users['authority'], $apps[0]);
        $s->approve($users['authority'], $offers[0], 'Acta sintética de ensayo concurrente.');
        $grade = DB::table('grades')->where('application_id', $apps[0])->value('id');
        $requests = [];
        foreach (['90', '95'] as $score) {
            $requests[] = app(Rectifications::class)->request($users['teacher'], $apps[0], $grade, $score, 'Corrección sintética concurrente.', 'ACTA-SINTETICA-CONCURRENCIA');
        }
        $m = DB::table('memberships')->where(['call_id' => $call, 'user_id' => $users['teacher']->id, 'role' => 'evaluator'])->first();
        $d = ['users' => array_map(fn ($u) => $u->id, $users), 'call' => $call, 'offers' => $offers, 'apps' => $apps, 'assignments' => $assignments, 'requests' => $requests, 'membership' => $m->id];
        file_put_contents($argv[1], json_encode($d));
        echo 'READY';
        exit;
    }
    // Cargar antes de la carrera simula una identidad de sesión ya iniciada.
    $u = User::findOrFail($d['users'][$mode === 'revoke' || $mode === 'grant' ? 'admin' : ($mode === 'correction' ? 'authority' : 'teacher')]);
    while (microtime(true) < $d['at']) {
        usleep(10000);
    }
    if ($mode === 'correction') {
        app(Rectifications::class)->decide($u, $d['requests'][(int) $argv[3]], true, 'Aprobación técnica concurrente sintética.');
    }
    if ($mode === 'revoke') {
        app(Administration::class)->revoke($u, $d['membership'], 1, 'Revocación concurrente sintética.');
    }
    if ($mode === 'grade') {
        $s->grade($u, $d['apps'][1], $d['assignments'][1], '85');
    }
    if ($mode === 'grant') {
        app(Administration::class)->grant($u, $d['call'], $d['users']['teacher'], 'evaluator', null, null, 'Renovación concurrente sintética.', 2);
    }
    if ($mode === 'verify') {
        $grade = DB::table('grades')->where('application_id', $d['apps'][0])->first();
        $second = DB::table('grades')->where('application_id', $d['apps'][1])->first();
        $noteAudit = $second ? DB::table('audit_events')->where('entity', 'grade:'.$second->id)->where('action', 'grade.confirmed')->value('id') : null;
        $revoke = DB::table('audit_events')->where('entity', 'membership:'.$d['membership'])->where('action', 'permission.revoked')->value('id');
        echo json_encode(['approved' => DB::table('correction_requests')->whereIn('id', $d['requests'])->where('status', 'approved')->count(), 'grade_version' => $grade->version, 'revisions' => DB::table('result_revisions')->where('offer_id', $d['offers'][0])->count(), 'publication_count' => DB::table('publications')->where('offer_id', $d['offers'][0])->count(), 'grade_was_before_revocation' => $second ? $noteAudit < $revoke : true, 'membership_version' => DB::table('memberships')->where('id', $d['membership'])->value('version')]);
        exit;
    }
    echo 'ACCEPTED';
} catch (ValidationException|HttpException $e) {
    echo 'REJECTED';
    exit(2);
}
