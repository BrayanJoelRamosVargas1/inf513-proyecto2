<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class SecuritySurfaceTest extends TestCase
{
    use RefreshDatabase;

    private function secured($response): void
    {
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_s01_revoked_session_redirect_keeps_security_headers(): void
    {
        $user = User::factory()->create(['security_version' => 0]);
        $this->actingAs($user)->withSession(['security_version' => 0]);
        DB::table('users')->where('id', $user->id)->update(['security_version' => 1]);
        $response = $this->get('/');
        $response->assertRedirect('/login');
        $this->assertGuest();
        $this->secured($response);
    }

    public function test_s02_malformed_and_overflowing_identifiers_return_not_found(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['abc', '0', '-1', '1.2', str_repeat('9', 60)] as $id) {
            foreach (["/postulaciones/$id", "/documentos/$id", "/convocatorias/$id", "/ofertas/$id/resultados"] as $path) {
                $this->get($path)->assertNotFound();
            }
        }
    }

    public function test_s03_login_response_disallows_embedded_objects(): void
    {
        $this->secured($this->get('/login')->assertOk());
    }

    public function test_s04_validation_errors_keep_security_headers(): void
    {
        $this->secured($this->postJson('/registro', [])->assertUnprocessable());
    }

    public function test_s05_forbidden_response_keeps_security_headers(): void
    {
        $this->actingAs(User::factory()->create());
        $this->secured($this->get('/usuarios')->assertForbidden());
    }

    public function test_s06_anonymous_private_route_redirect_keeps_security_headers(): void
    {
        $this->secured($this->get('/adjudicaciones')->assertRedirect('/login'));
    }

    public function test_s07_inactive_session_is_denied_with_security_headers(): void
    {
        $user = User::factory()->create(['active' => false]);
        $this->actingAs($user);
        $this->secured($this->get('/')->assertForbidden());
    }

    public function test_s08_not_found_response_keeps_security_headers(): void
    {
        $this->secured($this->get('/ruta-inexistente-de-prueba')->assertNotFound());
    }

    public function test_s09_cached_routes_keep_identifier_validation_in_a_fresh_process(): void
    {
        // F-05: no se toca bootstrap/cache: la caché de esta prueba se escribe en un archivo temporal propio.
        File::ensureDirectoryExists(storage_path('framework/testing'));
        // Laravel interpreta como relativa a la raíz toda ruta que no empiece por / o \ (también en Windows).
        $relative = 'storage/framework/testing/routes-s09-'.uniqid().'.php';
        $cache = base_path($relative);
        $original = $_ENV['APP_ROUTES_CACHE'] ?? null;
        $_ENV['APP_ROUTES_CACHE'] = $_SERVER['APP_ROUTES_CACHE'] = $relative;
        try {
            Artisan::call('route:cache');
            $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$callback = app('router')->getBindingCallback('id');
if (! is_callable($callback)) { echo 'MISSING_BINDING'; exit(0); }
$route = new Illuminate\Routing\Route('GET', '/probe/{id}', fn () => null);
$out = ['valid' => $callback('1', $route)];
foreach (['abc', str_repeat('9', 60)] as $value) {
    try { $callback($value, $route); $out[] = 'ACCEPTED'; }
    catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { $out[] = $e->getStatusCode(); }
}
echo json_encode($out);
PHP;
            $process = new Process([PHP_BINARY, '-r', $code], base_path(), ['APP_ROUTES_CACHE' => $relative]);
            $this->assertSame(0, $process->run(), $process->getErrorOutput());
            $this->assertSame(['valid' => 1, 404, 404], json_decode($process->getOutput(), true));
        } finally {
            @unlink($cache);
            if ($original === null) {
                unset($_ENV['APP_ROUTES_CACHE'], $_SERVER['APP_ROUTES_CACHE']);
            } else {
                $_ENV['APP_ROUTES_CACHE'] = $_SERVER['APP_ROUTES_CACHE'] = $original;
            }
        }
    }
}
