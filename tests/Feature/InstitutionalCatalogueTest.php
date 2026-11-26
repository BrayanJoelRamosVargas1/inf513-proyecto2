<?php

namespace Tests\Feature;

use App\Domain\InstitutionalCatalogue;
use App\Domain\InstitutionalRegister;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class InstitutionalCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private int $first;

    private int $second;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('categories')->insert(['name' => 'Docencia por asignatura', 'regime' => 'docencia', 'active' => true]);
        foreach ([1, 2] as $number) {
            $id = DB::table('calls')->insertGetId([
                'title' => '0'.$number.'/2026 · Referencia histórica',
                'mode' => 'HISTORICAL',
                'status' => 'archived',
                'source' => 'Convocatoria FICCT 0'.$number.'/2026',
                'opens_at' => '2026-01-01',
                'closes_at' => '2026-01-30',
                'exam_at' => '2026-02-02',
            ]);
            if ($number === 1) {
                $this->first = $id;
            } else {
                $this->second = $id;
            }
        }
    }

    private function import(): array
    {
        return app(InstitutionalCatalogue::class)->import($this->first, $this->second);
    }

    private function authority(): User
    {
        $user = User::factory()->create(['role' => 'authority']);
        DB::table('memberships')->insert(['call_id' => $this->first, 'user_id' => $user->id, 'role' => 'authority']);

        return $user;
    }

    private function commission(array $extra = []): array
    {
        return array_replace_recursive([
            'kind' => 'commission',
            'reference' => 'TEST-COMISION',
            'expected_version' => 0,
            'payload' => [
                'modality' => 'asignatura',
                'authority' => 'Autoridad sintética de prueba',
                'issued_on' => '2026-01-20',
                'members' => [['name' => 'Integrante ficticio', 'responsibility' => 'Responsabilidad de ensayo']],
                'notes' => 'Evidencia sintética; no acredita una designación.',
            ],
        ], $extra);
    }

    public function test_import_preserves_unknown_seats_and_inserts_49_subjects_and_66_relations(): void
    {
        $this->assertSame(['added_relations' => 66, 'subjects' => 49, 'relations' => 66, 'reopened_calls' => 0], $this->import());
        $this->assertSame(49, DB::table('offers')->where('call_id', $this->first)->count());
        $this->assertSame(17, DB::table('offers')->where('call_id', $this->second)->count());
        $this->assertSame(66, DB::table('offers')->whereNull('seats')->count());
    }

    public function test_repeated_import_does_not_duplicate_or_rewrite_records(): void
    {
        $this->import();
        $before = DB::table('offers')->orderBy('id')->get()->toJson();
        $this->assertSame(0, $this->import()['added_relations']);
        $this->assertSame($before, DB::table('offers')->orderBy('id')->get()->toJson());
    }

    public function test_existing_call_dates_status_and_source_are_preserved(): void
    {
        $before = DB::table('calls')->orderBy('id')->get()->toJson();
        $this->import();
        $this->assertSame($before, DB::table('calls')->orderBy('id')->get()->toJson());
    }

    public function test_second_call_failure_rolls_back_first_call_import(): void
    {
        DB::table('calls')->where('id', $this->second)->update(['mode' => 'DEMO']);
        try {
            $this->import();
            $this->fail('Must reject live/DEMO import');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(0, DB::table('offers')->count());
        $this->assertSame(0, DB::table('institutional_subjects')->count());
    }

    public function test_swapped_call_identifiers_are_rejected(): void
    {
        $this->expectException(HttpException::class);
        app(InstitutionalCatalogue::class)->import($this->second, $this->first);
    }

    public function test_unknown_seats_do_not_become_a_ranking_or_a_winner(): void
    {
        $this->import();
        $this->expectException(ValidationException::class);
        app(Selection::class)->ranking(DB::table('offers')->value('id'));
    }

    public function test_original_typography_and_grouping_are_not_silently_corrected(): void
    {
        $this->import();
        $this->assertSame('ECUACIONDES DIFERENCIALES', DB::table('institutional_subjects')->where('code', 'MAT207')->value('original_name'));
        $this->assertSame('ING. ROBÓTICA', DB::table('institutional_subject_offers')->where('call_id', $this->first)->where('printed_number', 53)->value('source_group'));
    }

    public function test_profile_keeps_conflicting_printed_dates_and_unknown_opening(): void
    {
        $this->import();
        $p = app(InstitutionalCatalogue::class)->forCall($this->first);
        $this->assertNull($p['calendars']['asignatura']['opening']);
        $this->assertSame('2026-02-02', $p['calendars']['asignatura']['exam_start']);
        $this->assertSame('2026-01-03', $p['calendars']['sala']['exam_start']);
        $this->assertNotEmpty($p['calendars']['sala']['conflict']);
        $this->assertSame('2026-02-13', app(InstitutionalCatalogue::class)->forCall($this->second)['calendars']['laboratorio']['exam_start']);
    }

    public function test_admission_thresholds_are_not_final_approval_thresholds(): void
    {
        $this->import();
        $p = app(InstitutionalCatalogue::class)->forCall($this->first);
        $this->assertStringContainsString('51', $p['modalities']['asignatura']['requirements']['ppa']);
        $this->assertStringContainsString('55', $p['modalities']['laboratorio']['requirements']['ppa']);
        foreach ($p['modalities'] as $m) {
            $this->assertNull($m['final_approval_threshold']);
        }
        $this->assertStringContainsString('≤5', $p['modalities']['laboratorio']['requirements']['permanencia']);
    }

    public function test_examination_regimes_remain_distinct_and_lab_terna_ambiguity_is_visible(): void
    {
        $d = app(InstitutionalCatalogue::class)->definitions()['profiles']['convocatoria-01']['modalities'];
        $this->assertSame(['Escrito' => 36, 'Práctico' => 24], $d['asignatura']['exam']['components']);
        $this->assertSame(['Práctico' => 36, 'Teórico' => 24], $d['laboratorio']['exam']['components']);
        $this->assertCount(2, $d['laboratorio']['commission']['members']);
        $this->assertNotEmpty($d['laboratorio']['commission']['ambiguity']);
    }

    public function test_original_downloads_return_exact_bytes_and_never_generic_documents(): void
    {
        foreach (app(InstitutionalCatalogue::class)->definitions()['sources'] as $key => $source) {
            $r = $this->get('/api/v1/reference-sources/'.$key)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertSame($source['sha256'], hash('sha256', $r->getContent()));
            $this->assertStringStartsWith('%PDF-', $r->getContent());
        }
        $this->get('/api/v1/reference-sources/cpd')->assertNotFound();
        $this->get('/api/v1/reference-sources/secret')->assertNotFound();
    }

    public function test_corrupt_source_is_blocked_instead_of_replaced(): void
    {
        $dir = storage_path('framework/testing/catalogue-'.uniqid());
        mkdir($dir.'/institutional', 0777, true);
        copy(resource_path('institutional/catalogue.json'), $dir.'/institutional/catalogue.json');
        file_put_contents($dir.'/institutional/resolucion.pdf', '%PDF-corrupt-test-only');
        app()->instance(InstitutionalCatalogue::class, new InstitutionalCatalogue($dir.'/institutional'));
        try {
            $this->get('/api/v1/reference-sources/resolucion')->assertStatus(409);
        } finally {
            unlink($dir.'/institutional/resolucion.pdf');
            unlink($dir.'/institutional/catalogue.json');
            rmdir($dir.'/institutional');
            rmdir($dir);
        }
    }

    public function test_student_sees_catalogue_without_receiving_authority_privileges(): void
    {
        $this->import();
        $student = User::factory()->create();
        $this->actingAs($student)->getJson('/api/v1/calls/'.$this->first)->assertOk()->assertJsonCount(49, 'documentary.subjects')->assertJsonPath('authority', false);
        $this->postJson('/api/v1/offers/'.DB::table('offers')->value('id').'/applications')->assertStatus(422);
        $this->postJson('/api/v1/calls/'.$this->first.'/institutional-register', $this->commission())->assertForbidden();
    }

    public function test_technical_admin_does_not_gain_institutional_registration(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->postJson('/api/v1/calls/'.$this->first.'/institutional-register', $this->commission())->assertForbidden();
    }

    public function test_commission_registration_is_versioned_and_does_not_assign_permissions(): void
    {
        $user = $this->authority();
        $before = DB::table('memberships')->count();
        $this->actingAs($user)->postJson('/api/v1/calls/'.$this->first.'/institutional-register', $this->commission())->assertCreated();
        $this->postJson('/api/v1/calls/'.$this->first.'/institutional-register', $this->commission())->assertStatus(409);
        $this->postJson('/api/v1/calls/'.$this->first.'/institutional-register', $this->commission(['expected_version' => 1]))->assertCreated();
        $this->assertSame(2, DB::table('institutional_register')->count());
        $this->assertSame($before, DB::table('memberships')->count());
        $this->assertSame(0, DB::table('publications')->count());
    }

    public function test_register_history_cannot_be_overwritten_in_postgresql(): void
    {
        app(InstitutionalRegister::class)->record($this->authority(), $this->first, $this->commission());
        $this->expectException(QueryException::class);
        DB::table('institutional_register')->update(['reference' => 'rewritten']);
    }

    public function test_missing_template_is_recorded_without_fake_download_or_homologation(): void
    {
        $data = [
            'kind' => 'template',
            'reference' => 'TEST-CPD-PENDIENTE',
            'expected_version' => 0,
            'payload' => [
                'modality' => 'asignatura',
                'title' => 'Formato pendiente',
                'issuer' => 'CPD Facultativo',
                'fields' => ['Campos pendientes de confirmar'],
                'notes' => 'Se solicita el formato auténtico; no es un documento emitido.',
            ],
        ];
        $this->actingAs($this->authority())->postJson('/api/v1/calls/'.$this->first.'/institutional-register', $data)->assertCreated();
        $payload = Selection::json(DB::table('institutional_register')->value('payload'));
        $this->assertStringContainsString('pendiente', $payload['validation']);
        $this->assertNull(DB::table('institutional_register')->value('source_id'));
    }

    private function cpdFixture(bool $withProfile = false): array
    {
        Storage::fake('local');
        $s = app(Selection::class);
        $authority = User::factory()->create(['role' => 'authority']);
        $reviewer = User::factory()->create(['role' => 'staff']);
        $student = User::factory()->create();
        $call = $s->createCall($authority, [
            'title' => 'TEST CPD separado',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM',
            'subject_limit' => 4,
            'source' => 'Datos sintéticos de aceptación',
        ]);
        DB::table('memberships')->insert(['user_id' => $reviewer->id, 'call_id' => $call, 'role' => 'reviewer']);
        $offer = $s->saveOffer($authority, $call, [
            'category_id' => DB::table('categories')->value('id'),
            'title' => 'TEST ASIGNATURA',
            'modality' => 'Presencial',
            'is_subject' => true,
            'seats' => 1,
            'documents' => ['cpd' => 'CPD de ensayo'],
            'requirements' => ['matricula' => 'Verificación de ensayo'],
            'weights' => ['Escrito' => 60, 'Práctico' => 40],
            'scheme' => 'DEMO_COMPONENTS_100',
            'source' => 'Ensayo aislado',
        ] + ($withProfile ? ['requirement_profile' => 'asignatura'] : []));
        $s->transition($authority, $call, 'published');
        $application = $s->apply($student, $offer);
        $doc = $s->upload($student, $application, 'cpd', UploadedFile::fake()->image('CPD-SINTETICO.png', 600, 850));
        foreach (array_keys(Selection::json(Selection::row('offers', $offer)->documents)) as $kind) {
            if ($kind !== 'cpd') {
                $s->upload($student, $application, $kind, UploadedFile::fake()->image('SINTETICO-'.$kind.'.png', 600, 850));
            }
        }
        $this->scanApplicantFixtures();
        $s->submit($student, $application);
        $input = [
            'kind' => 'cpd',
            'reference' => 'TEST-CPD-01',
            'expected_version' => 0,
            'payload' => [
                'modality' => 'asignatura',
                'application_id' => $application,
                'document_id' => $doc,
                'issued_on' => '2026-01-20',
                'score' => '20.00',
                'components' => ['nota' => '15.00', 'misma' => '4.00', 'otras' => '1.00'],
                'notes' => 'Respaldo de ensayo; no representa un formulario institucional real.',
            ],
        ];

        return [$reviewer, $call, $application, $doc, $input, $student];
    }

    public function test_cpd_components_are_preserved_without_replacing_the_existing_merit(): void
    {
        [$reviewer,$call,$application,$doc,$input] = $this->cpdFixture();
        app(InstitutionalRegister::class)->record($reviewer, $call, $input);
        $row = DB::table('institutional_register')->first();
        $p = Selection::json($row->payload);
        $this->assertSame('20.00', $p['component_sum']);
        $this->assertSame($doc, $p['document_id']);
        $this->assertNull(DB::table('applications')->where('id', $application)->value('merit'));
        $this->actingAs($reviewer)->getJson('/api/v1/applications/'.$application)->assertOk()->assertJsonCount(1, 'cpd_context.records');
    }

    public function test_cpd_cannot_use_another_modality_or_a_score_outside_the_component_limit(): void
    {
        [$reviewer,$call,$application,$doc,$input] = $this->cpdFixture();
        $url = '/api/v1/calls/'.$call.'/institutional-register';
        $bad = $input;
        $bad['payload']['modality'] = 'laboratorio';
        $this->actingAs($reviewer)->postJson($url, $bad)->assertStatus(422);
        $bad = $input;
        $bad['payload']['components']['nota'] = '25.00';
        $this->postJson($url, $bad)->assertStatus(422);
        $bad = $input;
        $bad['payload']['components']['nota'] = '15.001';
        $this->postJson($url, $bad)->assertStatus(422);
        $this->assertSame(0, DB::table('institutional_register')->count());
    }

    public function test_cpd_difference_requires_a_reason_and_does_not_override_officially_supplied_score(): void
    {
        [$reviewer,$call,$application,$doc,$input] = $this->cpdFixture();
        $input['payload']['score'] = '21.00';
        $url = '/api/v1/calls/'.$call.'/institutional-register';
        $this->actingAs($reviewer)->postJson($url, $input)->assertStatus(422);
        $input['payload']['difference_reason'] = 'Diferencia consignada para aclaración del CPD; no se corrige arbitrariamente.';
        $this->postJson($url, $input)->assertCreated();
        $p = Selection::json(DB::table('institutional_register')->value('payload'));
        $this->assertSame('21.00', $p['score']);
        $this->assertSame('20.00', $p['component_sum']);
    }

    public function test_cpd_record_verifies_current_bytes_instead_of_trusting_a_past_clean_scan(): void
    {
        [$reviewer,$call,$application,$doc,$input] = $this->cpdFixture();
        Storage::disk('local')->put(DB::table('documents')->where('id', $doc)->value('path'), 'changed test bytes');
        $this->actingAs($reviewer)->postJson('/api/v1/calls/'.$call.'/institutional-register', $input)->assertStatus(409);
        $this->assertSame(0, DB::table('institutional_register')->count());
    }

    public function test_postulant_cannot_verify_own_cpd_and_evaluator_never_receives_cpd_components(): void
    {
        [$reviewer,$call,$application,$doc,$input,$student] = $this->cpdFixture();
        $this->actingAs($student)->postJson('/api/v1/calls/'.$call.'/institutional-register', $input)->assertForbidden();
        $teacher = User::factory()->create(['role' => 'staff']);
        DB::table('memberships')->insert([
            'user_id' => $teacher->id,
            'call_id' => $call,
            'role' => 'evaluator',
        ]);
        DB::table('assignments')->insert([
            'user_id' => $teacher->id,
            'offer_id' => DB::table('applications')->where('id', $application)->value('offer_id'),
            'area' => 'Escrito',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($teacher)->getJson('/api/v1/applications/'.$application)->assertOk()->assertJsonPath('cpd_context', null);
    }

    public function test_documentary_profile_supplies_individual_requirements_instead_of_a_generic_checkbox(): void
    {
        [$reviewer,$call,$application] = $this->cpdFixture(true);
        $offer = Selection::row('offers', Selection::row('applications', $application)->offer_id);
        $requirements = Selection::json($offer->requirements);
        $this->assertCount(14, $requirements);
        $this->assertStringContainsString('UAGRM', $requirements['matricula']);
        $this->assertStringContainsString('80 %', $requirements['avance_nivel']);
        $this->assertStringContainsString('51', $requirements['ppa']);
        $this->assertSame('asignatura', $offer->requirement_profile);
    }

    public function test_only_documented_conditional_requirement_can_be_not_applicable_with_a_reason(): void
    {
        [$reviewer,$call,$application] = $this->cpdFixture(true);
        $this->actingAs($reviewer)->postJson('/api/v1/applications/'.$application.'/revision', [
            'decisions' => ['matricula' => 'not_applicable'],
            'reason' => 'Intento inválido de omitir matrícula.',
        ])->assertStatus(422);
        $this->postJson('/api/v1/applications/'.$application.'/revision', [
            'decisions' => ['graduacion_mora' => 'not_applicable'],
            'reason' => '',
        ])->assertStatus(422);
        $offer = Selection::row('offers', Selection::row('applications', $application)->offer_id);
        $keys = array_merge(array_map(fn ($k) => 'doc:'.$k, array_keys(Selection::json($offer->documents))), array_keys(Selection::json($offer->requirements)));
        $decisions = array_fill_keys($keys, 'meets');
        $decisions['graduacion_mora'] = 'not_applicable';
        $this->postJson('/api/v1/applications/'.$application.'/revision', [
            'decisions' => $decisions,
            'reason' => 'El respaldo de ensayo muestra plan aún en curso; no corresponde titulación en mora.',
        ])->assertOk();
        $this->getJson('/api/v1/applications/'.$application)->assertOk()->assertJsonPath('can_enable', true);
        app(Selection::class)->eligibility($reviewer, $application, true, 'Todos los criterios obligatorios contrastados en ensayo.');
        $this->assertSame('enabled', Selection::row('applications', $application)->eligibility);
    }

    public function test_calendar_accepts_unknown_opening_and_preserves_source_dates(): void
    {
        $this->import();
        $before = DB::table('institutional_call_profiles')->where('call_id', $this->first)->value('profile');
        $input = [
            'kind' => 'calendar',
            'reference' => 'TEST-CALENDAR',
            'expected_version' => 0,
            'payload' => [
                'modality' => 'asignatura',
                'timezone' => 'America/La_Paz',
                'opening' => null,
                'closing' => '2026-01-30T15:30',
                'exam_start' => '2026-02-02',
                'exam_end' => '2026-02-03',
                'notes' => 'Calendario de ensayo; fechas originales conservadas.',
            ],
        ];
        $this->actingAs($this->authority())->postJson('/api/v1/calls/'.$this->first.'/institutional-register', $input)->assertCreated();
        $this->assertSame($before, DB::table('institutional_call_profiles')->where('call_id', $this->first)->value('profile'));
        $p = Selection::json(DB::table('institutional_register')->value('payload'));
        $this->assertSame('America/La_Paz', $p['timezone']);
        $this->assertNull($p['opening']);
        $export = $this->get('/api/v1/calls/'.$this->first.'/institutional-register/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-01-30T15:30', $export);
        $this->assertStringContainsString('America/La_Paz', $export);
        $input['expected_version'] = 1;
        $input['payload']['opening'] = '2026-02-10';
        $this->postJson('/api/v1/calls/'.$this->first.'/institutional-register', $input)->assertStatus(422);
    }

    public function test_external_approval_cannot_be_claimed_without_an_original_source(): void
    {
        $input = $this->commission([
            'payload' => ['record_state' => 'external_approval_recorded', 'approval_reference' => 'TEST-REF', 'approval_authority' => 'Autoridad de ensayo'],
        ]);
        $this->actingAs($this->authority())->postJson('/api/v1/calls/'.$this->first.'/institutional-register', $input)->assertStatus(422);
        $this->assertSame(0, DB::table('institutional_register')->count());
    }

    public function test_commission_offer_must_belong_to_its_call(): void
    {
        $this->import();
        $other = DB::table('offers')->where('call_id', $this->second)->value('id');
        $this->actingAs($this->authority())->postJson('/api/v1/calls/'.$this->first.'/institutional-register', $this->commission(['payload' => ['offer_id' => $other]]))->assertStatus(422);
    }

    public function test_internal_register_export_is_authority_only_and_excludes_private_cpd(): void
    {
        $authority = $this->authority();
        app(InstitutionalRegister::class)->record($authority, $this->first, $this->commission());
        $response = $this->actingAs($authority)->get('/api/v1/calls/'.$this->first.'/institutional-register/export')->assertOk();
        $this->assertStringContainsString('TEST-COMISION', $response->streamedContent());
        $this->actingAs(User::factory()->create())->get('/api/v1/calls/'.$this->first.'/institutional-register/export')->assertForbidden();
    }

    public function test_submission_receipt_requires_a_submitted_snapshot_and_private_file_access(): void
    {
        [$reviewer,$call,$application,$doc,$input,$student] = $this->cpdFixture();
        $url = '/api/v1/applications/'.$application.'/receipt';
        $this->actingAs($student)->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($reviewer)->get($url)->assertOk();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $authority = User::factory()->create(['role' => 'authority']);
        DB::table('memberships')->insert(['user_id' => $authority->id, 'call_id' => $call, 'role' => 'authority']);
        $this->actingAs($authority)->get($url)->assertForbidden();
        $draft = app(Selection::class)->apply(User::factory()->create(), DB::table('applications')->where('id', $application)->value('offer_id'));
        $owner = User::find(DB::table('applications')->where('id', $draft)->value('user_id'));
        $this->actingAs($owner)->get('/api/v1/applications/'.$draft.'/receipt')->assertStatus(422);
    }

    public function test_institutional_mode_rejects_mixed_database_and_training_creation(): void
    {
        [$reviewer,$call,$application,$doc,$input,$student] = $this->cpdFixture();
        config(['portal.experience' => 'institutional']);
        $this->actingAs($student)->getJson('/api/v1/me')->assertStatus(503);
        $this->expectException(HttpException::class);
        app(Selection::class)->createCall(User::factory()->create(['role' => 'authority']), []);
    }

    public function test_legacy_historical_export_does_not_label_real_sources_as_fictitious(): void
    {
        $this->import();
        $response = $this->actingAs($this->authority())->get('/convocatorias/'.$this->first.'/reporte/xlsx')->assertOk();
        $bytes = $response->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'ficct-reference-');
        try {
            file_put_contents($path, $bytes);
            $book = IOFactory::load($path);
            $this->assertStringContainsString('Referencia histórica', $book->getActiveSheet()->getCell('A1')->getValue());
            $this->assertSame('Postulante', $book->getActiveSheet()->getCell('B3')->getValue());
            $this->assertSame(Selection::row('calls', $this->first)->title, $book->getActiveSheet()->getCell('A2')->getValue());
        } finally {
            unlink($path);
        }
    }

    public function test_documentary_seats_are_not_inferred_as_actual_historical_vacancies(): void
    {
        $this->import();
        $offer = DB::table('offers')->insertGetId([
            'call_id' => $this->first,
            'category_id' => DB::table('categories')->value('id'),
            'title' => 'Sala documental',
            'documents' => '{}',
            'requirements' => '{}',
            'weights' => '{}',
            'seats' => 20,
            'scheme' => 'PENDING_COMPOSITION',
            'source' => 'Convocatoria original de referencia',
        ]);
        $this->actingAs($this->authority())->getJson('/api/v1/offers/'.$offer.'/ranking')->assertOk()->assertJsonPath('ranking',null)->assertJsonPath('documentary_reference',true)->assertJsonPath('warning','La fuente histórica contiene ofertas, no resultados oficiales incorporados. No se infieren ganadores, cobertura ni vacantes.');
        $this->assertSame(20,DB::table('offers')->where('id',$offer)->value('seats'));
        $this->get('/ofertas/'.$offer.'/resultados')->assertOk()->assertDontSee('plazas sin cubrir por insuficiencia');
    }
}
