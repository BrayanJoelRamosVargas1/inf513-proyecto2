<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class InstitutionalExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_institutional_reference_seed_creates_only_archived_sources_without_test_users(): void
    {
        config(['portal.experience' => 'institutional']);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, DB::table('users')->count());
        $this->assertSame(0, DB::table('applications')->count());
        $this->assertSame(0, DB::table('calls')->where('mode', 'DEMO')->count());
        $this->assertSame(2, DB::table('calls')->where('status', 'archived')->count());
        $this->assertSame(49, DB::table('institutional_subjects')->count());
        $this->assertSame(66, DB::table('institutional_subject_offers')->count());
        $this->get('/p2')->assertOk();
        $this->get('/p2/entrenamiento')->assertNotFound();
    }

    public function test_samples_are_available_only_to_students_in_training(): void
    {
        $student = User::factory()->create();
        $this->actingAs($student)->get('/ejemplos/documentos/cpd')->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/ejemplos/documentos/cpd')->assertNotFound();
        config(['portal.experience' => 'institutional']);
        $this->get('/ejemplos/documentos/cpd')->assertNotFound();
    }

    public function test_institutional_seeding_refuses_existing_database_without_relabelling_it(): void
    {
        $user = User::factory()->create();
        $email = $user->email;
        config(['portal.experience' => 'institutional']);
        try {
            $this->seed(DatabaseSeeder::class);
            $this->fail('Expected refusal');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame($email, DB::table('users')->value('email'));
    }
}
