<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PublicVisitCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_counter_for_login_is_idempotent(): void
    {
        $key = (string) Str::uuid();
        $this->postJson('/api/v1/visits/public', ['path' => '/ingresar', 'key' => $key])
            ->assertOk()->assertJsonPath('total', 1);
        $this->postJson('/api/v1/visits/public', ['path' => '/ingresar', 'key' => $key])
            ->assertOk()->assertJsonPath('total', 1);
        $this->assertDatabaseCount('public_visit_receipts', 1);
    }

    public function test_guest_registration_counter_has_its_own_path(): void
    {
        $this->postJson('/api/v1/visits/public', ['path' => '/registro', 'key' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('path', '/registro');
        $this->assertDatabaseHas('page_visits', ['path' => '/registro', 'total' => 1]);
    }

    public function test_guest_counter_rejects_private_paths_and_receipt_reuse(): void
    {
        $key = (string) Str::uuid();
        $this->postJson('/api/v1/visits/public', ['path' => '/postulaciones', 'key' => $key])
            ->assertStatus(422);
        $this->postJson('/api/v1/visits/public', ['path' => '/ingresar', 'key' => $key])->assertOk();
        $this->postJson('/api/v1/visits/public', ['path' => '/registro', 'key' => $key])->assertStatus(409);
        $this->assertDatabaseCount('public_visit_receipts', 1);
    }

    public function test_public_counter_preserves_existing_totals_and_authenticated_receipts(): void
    {
        DB::table('page_visits')->insert([
            ['path' => '/cuenta', 'total' => 57],
            ['path' => '/ingresar', 'total' => 9],
        ]);
        $key = (string) Str::uuid();
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/visits/public', ['path' => '/ingresar', 'key' => $key])
                ->assertOk()->assertJsonPath('total', 10);
        }
        $this->assertDatabaseHas('page_visits', ['path' => '/cuenta', 'total' => 57]);
        $this->assertDatabaseCount('visit_receipts', 0);
        $this->assertDatabaseCount('public_visit_receipts', 1);
    }

    public function test_public_counter_requires_valid_receipts_and_does_not_open_private_endpoints(): void
    {
        $this->postJson('/api/v1/visits/public', ['path' => '/ingresar', 'key' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->postJson('/api/v1/visits', ['path' => '/cuenta', 'key' => (string) Str::uuid()])
            ->assertUnauthorized();
        $this->getJson('/api/v1/institutional/dossiers')->assertUnauthorized();
        $this->assertDatabaseCount('page_visits', 0);
    }

    public function test_public_views_do_not_consume_login_attempts(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/v1/visits/public', ['path' => '/ingresar', 'key' => (string) Str::uuid()])
                ->assertOk();
        }
        $this->postJson('/api/v1/login', ['email' => 'no-existe@example.test', 'password' => 'invalid-password'])
            ->assertUnprocessable()->assertJsonPath('message', 'Credenciales incorrectas o cuenta desactivada.');
    }
}
