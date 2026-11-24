<?php

/** Prepares identities and draft configuration only; no applications, documents or results. */
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Selection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

if (! str_starts_with(config('database.connections.pgsql.database'), 'ficct_applicants_') || config('database.connections.pgsql.host') !== '127.0.0.1') {
    throw new RuntimeException('Existing isolated acceptance database required.');
}
$output = $argv[1] ?? '';
$parent = realpath(dirname($output));
$private = realpath(base_path('.local/academic-close'));
if (! $parent || ! $private || ! str_starts_with($parent.DIRECTORY_SEPARATOR, $private.DIRECTORY_SEPARATOR) || file_exists($output)) {
    throw new RuntimeException('New private fixture file required; never overwrite existing history.');
}
$fixture = DB::transaction(function () {
    $tag = bin2hex(random_bytes(5));
    $actors = [];
    $models = [];
    $roles = ['authority' => 'authority', 'reviewer' => 'staff'];
    foreach (range(0, 4) as $n) {
        $roles['evaluator'.$n] = 'staff';
    }
    foreach (range('A', 'L') as $letter) {
        $roles[$letter] = 'student';
    }
    foreach ($roles as $key => $role) {
        $password = bin2hex(random_bytes(18));
        $user = new User;
        $user->forceFill(['name' => 'Competencia '.$key.' '.$tag, 'email' => 'competencia-'.$key.'-'.$tag.'@example.test', 'password' => $password, 'role' => $role, 'faculty' => 'FICCT', 'career' => 'Sistemas'])->save();
        $models[$key] = $user;
        $actors[$key] = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'password' => $password];
    }
    $categories = [];
    foreach (['software' => 'docencia', 'redes' => 'laboratorio', 'electronica' => 'laboratorio'] as $key => $regime) {
        $categories[$key] = DB::table('categories')->insertGetId(['name' => 'Ensayo '.$key.' '.$tag, 'regime' => $regime, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
    $service = app(Selection::class);
    $calls = [];
    foreach ([1, 2] as $number) {
        $calls[$number] = $service->createCall($models['authority'], ['title' => 'Competencia sintetica '.$number.' '.$tag, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(7), 'exam_at' => now()->addDays(8), 'scope' => 'UAGRM', 'source' => 'Ensayo aislado de competencia; sin valor institucional.']);
        DB::table('memberships')->insert(['call_id' => $calls[$number], 'user_id' => $models['reviewer']->id, 'role' => 'reviewer']);
        foreach (range(0, 4) as $n) {
            DB::table('memberships')->insert(['call_id' => $calls[$number], 'user_id' => $models['evaluator'.$n]->id, 'role' => 'evaluator']);
        }
    }
    // Amounts and cases are test data, never institutional rules or thresholds.
    $definitions = [
        'software' => [1, 'software', 2, ['Software' => '100'], ['Software' => 0]],
        'redes' => [1, 'redes', 2, ['Redes' => '75', 'Soporte' => '25'], ['Redes' => 1, 'Soporte' => 2]],
        'electronica' => [1, 'electronica', 4, ['Electrónica' => '75', 'Soporte' => '25'], ['Electrónica' => 3, 'Soporte' => 4]],
        'exacto' => [1, 'software', 2, ['Software' => '100'], ['Software' => 0]],
        'empate_fuera' => [1, 'software', 1, ['Software' => '100'], ['Software' => 4]],
        'pendiente' => [1, 'redes', 1, ['Redes' => '100'], ['Redes' => 2]],
        'segunda' => [2, 'software', 1, ['Software' => '50', 'Soporte' => '50'], ['Software' => 3, 'Soporte' => 4]],
        'sin_habilitados' => [2, 'electronica', 1, ['Electrónica' => '100'], ['Electrónica' => 3]],
    ];
    $offers = [];
    foreach ($definitions as $key => [$number, $type, $seats, $weights, $assigned]) {
        $requirements = ['activo' => 'Criterio sintetico de estudiante activo'];
        if ($type !== 'software') {
            $requirements[$type] = 'Condicion sintetica especifica de '.$type;
        }
        $id = $service->saveOffer($models['authority'], $calls[$number], ['category_id' => $categories[$type], 'title' => 'Competencia '.$key.' '.$tag, 'seats' => $seats, 'is_subject' => false, 'modality' => 'Presencial', 'documents' => ['cpd' => 'Formulario de meritos sintetico para '.$type], 'requirements' => $requirements, 'weights' => $weights, 'scheme' => 'DEMO_AREAS_100', 'source' => 'Datos sinteticos: escala 0..100 por area y examen proporcional /60.']);
        foreach ($assigned as $area => $n) {
            $service->assign($models['authority'], $id, $models['evaluator'.$n]->id, $area);
        }
        $offers[$key] = ['id' => $id, 'call' => $calls[$number], 'type' => $type, 'category_id' => $categories[$type], 'seats' => $seats, 'weights' => $weights, 'assigned' => $assigned, 'requirements' => $requirements];
    }
    $cases = [];
    $add = function ($offer, $student, $merit, $scores, $extra = []) use (&$cases) {
        $cases[] = array_merge(['offer' => $offer, 'student' => $student, 'merit' => $merit, 'scores' => $scores], $extra);
    };
    foreach (['A' => ['37.50', '90'], 'B' => ['33.25', '90'], 'C' => ['33.25', '90'], 'D' => ['27.80', '80'], 'E' => ['21.40', '80']] as $student => [$merit, $score]) {
        $add('software', $student, $merit, ['Software' => $score]);
    }
    foreach (['A' => '32', 'F' => '22', 'G' => '22', 'J' => '22'] as $student => $merit) {
        $add('redes', $student, $merit, ['Redes' => '80', 'Soporte' => '80']);
    }
    foreach (['H' => ['32', '80'], 'I' => ['0', '0'], 'A' => ['12', '80']] as $student => [$merit, $score]) {
        $add('electronica', $student, $merit, ['Electrónica' => $score, 'Soporte' => $score]);
    }
    $add('exacto', 'J', '30', ['Software' => '50']);
    $add('exacto', 'K', '20', ['Software' => '50']);
    $add('empate_fuera', 'D', '36', ['Software' => '90']);
    $add('empate_fuera', 'E', '22', ['Software' => '80']);
    $add('empate_fuera', 'L', '22', ['Software' => '80']);
    $add('pendiente', 'F', '20', [], ['pending' => true]);
    $add('pendiente', 'G', null, [], ['disabled' => true]);
    $add('segunda', 'A', '8', ['Software' => '10', 'Soporte' => '30']);
    $add('segunda', 'L', '36', ['Software' => '80', 'Soporte' => '100']);
    $add('sin_habilitados', 'G', null, [], ['disabled' => true]);
    $add('sin_habilitados', 'H', null, [], ['disabled' => true]);

    return ['tag' => $tag, 'actors' => $actors, 'categories' => $categories, 'calls' => $calls, 'offers' => $offers, 'cases' => $cases, 'preparation' => 'Fixtures: identities, scoped memberships, categories, draft calls/offers and assignments only. Academic outcomes must be created through React.'];
});
file_put_contents($output, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo json_encode(['result' => 'PREPARED', 'actors' => count($fixture['actors']), 'calls' => count($fixture['calls']), 'offers' => count($fixture['offers']), 'cases' => count($fixture['cases']), 'academic_results_inserted' => 0]);
