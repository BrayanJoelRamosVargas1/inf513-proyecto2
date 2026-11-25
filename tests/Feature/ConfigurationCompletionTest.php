<?php

namespace Tests\Feature;

use App\Domain\Administration;
use App\Domain\Selection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ConfigurationCompletionTest extends TestCase
{
    use RefreshDatabase;

    private User $authority;

    private User $admin;

    private int $call;

    private int $category;

    private function fields(): array
    {
        return ['title' => 'Identificación explícita DEMO', 'opens_at' => now()->subDay()->toDateTimeString(), 'closes_at' => now()->addDay()->toDateTimeString(), 'exam_at' => now()->addDays(2)->toDateTimeString(), 'scope' => 'UAGRM', 'source' => 'Datos sintéticos sin validez institucional'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->authority = User::factory()->create(['role' => 'authority']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->call = app(Selection::class)->createCall($this->authority, $this->fields());
        $this->category = DB::table('categories')->insertGetId(['name' => 'Tipo original DEMO', 'regime' => 'docencia', 'active' => true]);
    }

    private function offer(int $call): int
    {
        return app(Selection::class)->saveOffer($this->authority, $call, ['category_id' => $this->category, 'title' => 'Oferta de prueba', 'seats' => 1, 'is_subject' => false, 'modality' => 'Presencial', 'documents' => ['cpd' => 'CPD sintético'], 'requirements' => ['activo' => 'Activo'], 'weights' => ['Soporte' => '100'], 'scheme' => 'DEMO_AREAS_100', 'source' => 'Configuración de prueba']);
    }

    public function test_d01_call_creation_stores_explicit_year_and_number_and_faculty_scope(): void
    {
        $this->actingAs($this->authority)->post('/convocatorias', array_merge($this->fields(), ['management_year' => '2026', 'call_number' => '01', 'scope' => 'faculty', 'scope_value' => 'FICCT']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('calls', ['management_year' => 2026, 'call_number' => '01', 'scope' => 'faculty', 'scope_value' => 'FICCT']);
    }

    public function test_d02_draft_edit_preserves_identification_when_old_client_omits_it(): void
    {
        $this->actingAs($this->authority)->post('/convocatorias/'.$this->call.'/editar', $this->fields() + ['management_year' => '2026', 'call_number' => '02'])->assertSessionHasNoErrors();
        $this->post('/convocatorias/'.$this->call.'/editar', $this->fields())->assertSessionHasNoErrors();
        $this->assertDatabaseHas('calls', ['id' => $this->call, 'management_year' => 2026, 'call_number' => '02']);
        $this->get('/convocatorias/'.$this->call)->assertOk()->assertSee('2026')->assertSee('02');
    }

    public function test_d03_published_identification_cannot_be_changed(): void
    {
        $this->offer($this->call);
        app(Selection::class)->transition($this->authority, $this->call, 'published');
        $this->actingAs($this->authority)->post('/convocatorias/'.$this->call.'/editar', $this->fields() + ['management_year' => '2027', 'call_number' => '99'])->assertSessionHasErrors('proceso');
        $this->assertDatabaseHas('calls', ['id' => $this->call, 'management_year' => null, 'call_number' => null]);
    }

    public function test_d04_legacy_identification_stays_unknown_and_invalid_input_is_rejected(): void
    {
        $this->assertDatabaseHas('calls', ['id' => $this->call, 'management_year' => null, 'call_number' => null]);
        $this->actingAs($this->authority)->post('/convocatorias', $this->fields() + ['management_year' => '42', 'call_number' => str_repeat('x', 41)])->assertSessionHasErrors(['management_year', 'call_number']);
        $this->assertDatabaseCount('calls', 1);
    }

    public function test_d05_only_admin_can_update_category(): void
    {
        foreach ([$this->authority, User::factory()->create()] as $u) {
            $this->actingAs($u)->post('/categorias/'.$this->category.'/editar', ['name' => 'Otro', 'regime' => 'docencia', 'active' => '0', 'reason' => 'Intento sintético no autorizado'])->assertForbidden();
        }
        $this->assertDatabaseHas('categories', ['id' => $this->category, 'name' => 'Tipo original DEMO', 'active' => true]);
    }

    public function test_d06_unused_category_can_be_edited_activated_and_deactivated_with_audit(): void
    {
        $data = ['name' => 'Tipo actualizado DEMO', 'regime' => 'laboratorio', 'active' => '0', 'reason' => 'Cambio sintético del catálogo.'];
        $this->actingAs($this->admin)->post('/categorias/'.$this->category.'/editar', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categories', ['id' => $this->category, 'name' => $data['name'], 'regime' => 'laboratorio', 'active' => false]);
        $data['active'] = '1';
        $this->post('/categorias/'.$this->category.'/editar', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categories', ['id' => $this->category, 'active' => true]);
        $this->assertSame(2, DB::table('audit_events')->where('action', 'category.updated')->count());
    }

    public function test_d07_used_category_keeps_identity_and_disabling_does_not_change_existing_offer(): void
    {
        $id = $this->offer($this->call);
        app(Selection::class)->transition($this->authority, $this->call, 'published');
        $before = DB::table('offers')->where('id', $id)->first();
        $data = ['name' => 'Tipo original DEMO', 'regime' => 'docencia', 'active' => '0', 'reason' => 'Desactivación para futuras ofertas.'];
        $this->actingAs($this->admin)->post('/categorias/'.$this->category.'/editar', $data)->assertSessionHasNoErrors();
        $this->assertEquals($before, DB::table('offers')->where('id', $id)->first());
        $data['name'] = 'Nombre que altera el histórico';
        $this->post('/categorias/'.$this->category.'/editar', $data)->assertSessionHasErrors('proceso');
        $this->assertDatabaseHas('categories', ['id' => $this->category, 'name' => 'Tipo original DEMO']);
        $next = app(Selection::class)->createCall($this->authority, $this->fields());
        $this->expectException(ValidationException::class);
        $this->offer($next);
    }

    public function test_d08_category_change_requires_reason_and_cannot_mutate_other_columns(): void
    {
        $this->actingAs($this->admin)->post('/categorias/'.$this->category.'/editar', ['name' => 'Tipo original DEMO', 'regime' => 'docencia', 'active' => '0', 'reason' => 'corto'])->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('categories', ['id' => $this->category, 'active' => true]);
        app(Administration::class)->editCategory($this->admin, $this->category, ['name' => 'Tipo original DEMO', 'regime' => 'docencia', 'active' => false, 'id' => 999], 'Motivo sintético suficientemente largo.');
        $this->assertDatabaseHas('categories', ['id' => $this->category, 'active' => false]);
        $this->assertDatabaseMissing('categories', ['id' => 999]);
    }
}
