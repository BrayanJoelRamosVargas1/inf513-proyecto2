<?php

require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->useStoragePath($argv[2]);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! str_starts_with(config('database.connections.pgsql.database'), 'ficct_walkthrough_')) {
    throw new RuntimeException('Solo base sintética del recorrido.');
}
$users = [];
foreach (['admin', 'autoridad', 'revisor', 'evaluador', 'postulante', 'suplente', 'externo'] as $role) {
    $users[$role] = App\Models\User::where('email', $role.'@recorrido.ficct.test')->firstOrFail();
}
$s = app(App\Domain\Selection::class);
$p = app(App\Domain\Participation::class);
$a = $users['autoridad'];
$r = $users['revisor'];
$student = $users['postulante'];
$attrs = ['title' => 'Fixture sintético de cobertura extendida', 'opens_at' => now()->subDay()->toIso8601String(), 'closes_at' => now()->addDay()->toIso8601String(), 'exam_at' => now()->addDays(2)->toIso8601String(), 'scope' => 'UAGRM', 'source' => 'Prueba sintética, sin valor institucional'];
$draft = $s->createCall($a, $attrs);
$category = Illuminate\Support\Facades\DB::table('categories')->value('id');
$offer = ['title' => 'Oferta sintética de formulario', 'category_id' => $category, 'seats' => 1, 'modality' => 'Presencial', 'is_subject' => false, 'documents' => ['cpd' => 'CPD sintético', 'ci' => 'Identidad sintética'], 'requirements' => ['activo' => 'Declaración sintética'], 'weights' => ['Prueba' => '100'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Configuración de ensayo sin validez'];
$draftOffer = $s->saveOffer($a, $draft, $offer);
$pub = $s->createCall($a, $attrs);
foreach (['revisor' => 'reviewer', 'evaluador' => 'evaluator'] as $key => $role) {
    Illuminate\Support\Facades\DB::table('memberships')->insert(['call_id' => $pub, 'user_id' => $users[$key]->id, 'role' => $role]);
}
$pubOffer = $s->saveOffer($a, $pub, $offer);
$s->transition($a, $pub, 'published');
$observed = $s->apply($student, $pubOffer);
foreach (['cpd', 'ci'] as $kind) {
    $s->upload($student, $observed, $kind, Illuminate\Http\UploadedFile::fake()->image('sintetico-'.$kind.'.png'));
}
$s->submit($student, $observed);
$s->review($r, $observed, ['doc:cpd' => 'observed', 'doc:ci' => 'meets', 'activo' => 'meets'], 'Observación sintética de prueba de formularios');
$amendment = $p->open($r, $observed, ['cpd'], now()->addDay()->toIso8601String(), 'Apertura sintética de cobertura de formularios', 'Evidencia sintética sin procedimiento institucional');
$baseApp = Illuminate\Support\Facades\DB::table('applications')->where('user_id', $student->id)->whereNotNull('final')->where('participation', 'active')->first();
$correction = app(App\Domain\Rectifications::class)->request($r, $baseApp->id, null, '21.00', 'Solicitud sintética de rectificación para inspección', 'Evidencia sintética sin validez administrativa');
$data = ['draft' => $draft, 'draft_offer' => $draftOffer, 'published' => $pub, 'observed_app' => $observed, 'amendment' => $amendment, 'base_app' => 1, 'base_call' => 1, 'offer' => 1, 'base_document' => Illuminate\Support\Facades\DB::table('documents')->where('application_id', 1)->value('id'), 'correction' => $correction];
file_put_contents($argv[3], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "FIXTURES_READY\n";
