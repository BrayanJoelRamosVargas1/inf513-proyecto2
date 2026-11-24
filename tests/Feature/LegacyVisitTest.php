<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class LegacyVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_auth_views_add_visits_without_resetting_existing_totals(): void
    {
        DB::table('page_visits')->insert(['path' => '/login', 'total' => 17]);
        $this->get('/login')->assertOk()->assertSee('Visitas de esta vista: 18');
        $this->get('/login')->assertOk()->assertSee('Visitas de esta vista: 19');
        $this->get('/registro')->assertOk()->assertSee('Visitas de esta vista: 1');
        $this->assertDatabaseHas('page_visits', ['path' => '/login', 'total' => 19]);
        $this->assertSame(2, DB::table('access_events')->where('event', 'page.view')->where('resource', '/login')->count());
    }

    public function test_spa_shell_and_api_do_not_add_a_second_legacy_visit(): void
    {
        $this->get('/p2')->assertOk();
        $this->get('/p2/registro')->assertOk();
        $this->assertDatabaseCount('page_visits', 0);
        $receipt = ['path' => '/registro', 'key' => (string) Str::uuid()];
        $this->postJson('/api/v1/visits/public', $receipt)->assertOk()->assertJsonPath('total', 1);
        $this->postJson('/api/v1/visits/public', $receipt)->assertOk()->assertJsonPath('total', 1);
        $this->assertDatabaseCount('access_events', 0);
        $this->assertDatabaseCount('public_visit_receipts', 1);
    }

    public function test_an_html_error_response_is_not_registered_as_a_successful_page_view(): void
    {
        Route::get('/native-failure', fn () => response()->view('auth', ['register' => false], 500));
        $this->get('/native-failure')->assertStatus(500);
        $this->assertDatabaseMissing('page_visits', ['path' => '/native-failure']);
        $this->assertDatabaseCount('access_events', 0);
    }

    public function test_legacy_authentication_records_failed_successful_and_logout_events_with_ip_once(): void
    {
        $user = User::factory()->create(['password' => 'Synthetic-secret-12345']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.9'])->post('/login', ['email' => $user->email, 'password' => 'Wrong-secret-12345'])->assertRedirect()->assertSessionHasErrors('email');
        $this->assertDatabaseHas('access_events', ['event' => 'login.failed', 'ip' => '192.0.2.9', 'status' => 422]);
        $this->post('/login', ['email' => $user->email, 'password' => 'Synthetic-secret-12345'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, DB::table('access_events')->where('event', 'login.success')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('audit_events')->where('action', 'session.login')->where('user_id', $user->id)->count());
        $this->post('/salir')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame(1, DB::table('access_events')->where('event', 'logout')->where('user_id', $user->id)->count());
    }
    public function test_legacy_and_api_validation_use_spanish_even_if_environment_locale_is_english(): void
    {
        config(['app.locale' => 'en']);
        app()->setLocale('en');
        $this->postJson('/login', [])->assertUnprocessable()->assertJsonPath('errors.email.0', 'El campo correo es obligatorio.');
        app()->setLocale('en');
        $this->postJson('/api/v1/login', [])->assertUnprocessable()->assertJsonPath('errors.email.0', 'El campo correo es obligatorio.');
    }

}
