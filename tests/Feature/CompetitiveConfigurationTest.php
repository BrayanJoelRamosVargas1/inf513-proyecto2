<?php

namespace Tests\Feature;

use App\Domain\Selection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CompetitiveConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_rejects_a_configuration_that_cannot_supply_the_existing_merit_backing(): void
    {
        $authority = User::factory()->create(['role' => 'authority']);
        $service = app(Selection::class);
        $call = $service->createCall($authority, [
            'title' => 'Configuración sintética',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addDay(),
            'exam_at' => now()->addDays(2),
            'scope' => 'UAGRM',
            'source' => 'Ensayo de consistencia técnica',
        ]);
        $category = DB::table('categories')->insertGetId(['name' => 'Tipo sintético', 'regime' => 'docencia', 'active' => true]);
        $data = [
            'category_id' => $category,
            'title' => 'Oferta sintética',
            'seats' => 1,
            'is_subject' => false,
            'modality' => 'Presencial',
            'documents' => ['identidad' => 'Identidad sintética'],
            'requirements' => ['activo' => 'Condición sintética'],
            'weights' => ['Software' => '100'],
            'scheme' => 'DEMO_AREAS_100',
            'source' => 'Configuración sintética',
        ];
        $offer = $service->saveOffer($authority, $call, $data);

        $this->actingAs($authority)->postJson('/api/v1/calls/'.$call.'/transition', ['status' => 'published'])
            ->assertUnprocessable()->assertJsonValidationErrors('proceso');
        $this->assertDatabaseHas('calls', ['id' => $call, 'status' => 'draft']);
        $this->assertDatabaseMissing('audit_events', ['call_id' => $call, 'action' => 'call.transition']);

        // Correct the draft through the supported domain operation; never rewrite a frozen offer.
        $data['documents']['cpd'] = 'Formulario de respaldo de méritos';
        $service->saveOffer($authority, $call, $data, $offer);
        $this->postJson('/api/v1/calls/'.$call.'/transition', ['status' => 'published'])->assertOk();
        $this->assertDatabaseHas('calls', ['id' => $call, 'status' => 'published']);
    }
}
