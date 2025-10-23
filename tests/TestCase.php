<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\LoginUser;
use Tymon\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed the super admin for tests that need it
        $this->createSuperAdmin();
    }

    /**
     * Create a test super admin user
     */
    protected function createSuperAdmin(): LoginUser
    {
        return LoginUser::factory()->superadmin()->passwordChanged()->create([
            'username' => 'superadmin',
            'password' => bcrypt('admin123'),
            'employee_id' => 'SA001',
            'email' => 'superadmin@test.com',
        ]);
    }

    /**
     * Create and authenticate a user for API testing
     */
    protected function authenticateAs(LoginUser $user): string
    {
        $token = JWTAuth::fromUser($user);
        return $token;
    }

    /**
     * Helper to make authenticated API requests
     */
    protected function actingAsUser(LoginUser $user)
    {
        $token = $this->authenticateAs($user);
        return $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Helper to assert API response structure
     */
    protected function assertApiResponseStructure($response, $expectedHttpCode = 200)
    {
        $response->assertStatus($expectedHttpCode);
        $response->assertJsonStructure([
            'http_code',
            'message',
            'error_id',
            'data',
        ]);
        
        $responseData = $response->json();
        $this->assertEquals($expectedHttpCode, $responseData['http_code']);
    }

    /**
     * Helper to assert successful API response
     */
    protected function assertApiSuccess($response, $message = null)
    {
        $this->assertApiResponseStructure($response, 200);
        
        if ($message) {
            $response->assertJson(['message' => $message]);
        }
        
        $response->assertJson(['error_id' => null]);
    }

    /**
     * Helper to assert API error response
     */
    protected function assertApiError($response, $expectedHttpCode, $message = null)
    {
        $this->assertApiResponseStructure($response, $expectedHttpCode);
        
        if ($message) {
            $response->assertJson(['message' => $message]);
        }
        
        $responseData = $response->json();
        $this->assertNotNull($responseData['error_id']);
    }

    public function createApplication()
{
    $app = require __DIR__ . '/../bootstrap/app.php';

    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    return $app;
}
}
