<?php

use App\Domain\Selection;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.'.config('database.default').'.database') !== 'ficct_test') {
    throw new RuntimeException('Solo ficct_test.');
}
require __DIR__.'/concurrency_documents.php';
$s = app(Selection::class);
$d = json_decode(file_get_contents($argv[1]), true);
$mode = $argv[2];
try {
    if ($mode === 'setup') {
        $suffix = bin2hex(random_bytes(5));
        $u = [];
        foreach (['authority', 'student', 'teacher'] as $role) {
            $user = new User;
            $user->forceFill(['name' => 'RACE-'.$role, 'email' => $role.$suffix.'@example.test', 'password' => Hash::make(bin2hex(random_bytes(12))), 'role' => $role === 'authority' ? 'authority' : ($role === 'student' ? 'student' : 'staff')])->save();
            $u[$role] = $user;
        }
        $call = $s->createCall($u['authority'], ['title' => 'Race '.$suffix, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'subject_limit' => 4, 'source' => 'Prueba de concurrencia aislada', 'scope' => 'UAGRM']);
        $category = DB::table('categories')->insertGetId(['name' => 'Race '.$suffix, 'regime' => 'docencia', 'active' => true]);
        $offers = [];
        foreach (range(1, 5) as $i) {
            $offers[] = $s->saveOffer($u['authority'], $call, ['category_id' => $category, 'title' => 'Subject '.$i, 'seats' => 1, 'is_subject' => true, 'modality' => 'DEMO', 'documents' => ['cpd' => 'CPD'], 'requirements' => ['activo' => 'Activo'], 'weights' => ['Software' => '100'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Fixture']);
        }
        $s->transition($u['authority'], $call, 'published');
        foreach (array_slice($offers, 0, 3) as $oid) {
            $s->apply($u['student'], $oid);
        }
        $d = ['authority' => $u['authority']->id, 'student' => $u['student']->id, 'teacher' => $u['teacher']->id, 'call' => $call, 'offers' => $offers, 'at' => microtime(true) + 3];
        // Segundo ámbito para comprobar confirmación doble y la última plaza.
        $selection = $s->createCall($u['authority'], ['title' => 'Selection race '.$suffix, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'exam_at' => now()->addDays(2), 'source' => 'Fixture', 'scope' => 'UAGRM']);
        $offer = $s->saveOffer($u['authority'], $selection, ['category_id' => $category, 'title' => 'Last seat', 'seats' => 1, 'is_subject' => false, 'modality' => 'DEMO', 'documents' => ['cpd' => 'CPD'], 'requirements' => ['activo' => 'Activo'], 'weights' => ['Software' => '100'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Fixture']);
        $s->transition($u['authority'], $selection, 'published');
        $candidate = $s->apply($u['student'], $offer);
        DB::table('memberships')->insert(['call_id' => $selection, 'user_id' => $u['authority']->id, 'role' => 'reviewer']);
        ficctRaceDocument($u['student'], $candidate);
        $s->submit($u['student'], $candidate);
        $s->review($u['authority'], $candidate, ['doc:cpd' => 'meets', 'activo' => 'meets'], 'Cotejo sintético para ensayo de concurrencia.');
        $s->eligibility($u['authority'], $candidate, true, 'Habilitación sintética, sin valor institucional.');
        $s->merit($u['authority'], $candidate, '40', 'CPD sintético de ensayo');
        DB::table('memberships')->insert(['call_id' => $selection, 'user_id' => $u['teacher']->id, 'role' => 'evaluator']);
        $s->assign($u['authority'], $offer, $u['teacher']->id, 'Software');
        $s->transition($u['authority'], $selection, 'closed');
        $s->transition($u['authority'], $selection, 'evaluation');
        $d += ['selection_call' => $selection, 'selection_offer' => $offer, 'candidate' => $candidate, 'assignment' => DB::table('assignments')->where('offer_id', $offer)->value('id')];
        file_put_contents($argv[1], json_encode($d));
        echo 'READY';
        exit;
    }
    while (microtime(true) < $d['at']) {
        usleep(10000);
    }
    if ($mode === 'apply') {
        $s->apply(User::findOrFail($d['student']), (int) $argv[3]);
    }
    if ($mode === 'calculate') {
        $s->calculate(User::findOrFail($d['authority']), $d['candidate']);
    }
    if ($mode === 'approve') {
        $s->approve(User::findOrFail($d['authority']), $d['selection_offer'], 'Acta de prueba concurrente sobre última plaza DEMO.');
    }
    if ($mode === 'grade') {
        $s->grade(User::findOrFail($d['teacher']), $d['candidate'], $d['assignment'], '100');
    }
    if ($mode === 'verify') {
        $db = DB::class;
        $counts = ['applications' => $db::table('applications')->join('offers', 'offers.id', '=', 'applications.offer_id')->where('offers.call_id', $d['call'])->count(), 'selected' => $db::table('applications')->where('offer_id', $d['selection_offer'])->where('selection', 'selected')->count(), 'publications' => $db::table('publications')->where('offer_id', $d['selection_offer'])->count(), 'grades' => $db::table('grades')->where('application_id', $d['candidate'])->count()];
        echo json_encode($counts);
        exit;
    }
    echo 'ACCEPTED';
} catch (ValidationException $e) {
    echo 'REJECTED';
    exit(2);
}
