<?php

namespace Tests\Feature\Observability;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadinessEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_returns_healthy_json_structure(): void
    {
        $response = $this->getJson('/_readiness');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'checks' => [
                'database' => ['ok', 'driver'],
                'cache' => ['ok', 'driver'],
                'queue' => ['ok', 'driver'],
            ],
            'timestamp',
        ]);
        $response->assertJson(['status' => 'healthy']);
    }

    public function test_readiness_database_check_reports_driver(): void
    {
        $response = $this->getJson('/_readiness');

        $response->assertOk();
        $data = $response->json();

        $this->assertTrue($data['checks']['database']['ok']);
        $this->assertNotEmpty($data['checks']['database']['driver']);
    }

    public function test_readiness_cache_check_passes(): void
    {
        $response = $this->getJson('/_readiness');

        $response->assertOk();
        $data = $response->json();

        $this->assertTrue($data['checks']['cache']['ok']);
    }

    public function test_readiness_queue_check_validates_driver(): void
    {
        $response = $this->getJson('/_readiness');

        $response->assertOk();
        $data = $response->json();

        $this->assertTrue($data['checks']['queue']['ok']);
        $this->assertContains($data['checks']['queue']['driver'], ['sync', 'database', 'redis', 'beanstalkd', 'sqs']);
    }

    public function test_readiness_returns_503_when_queue_driver_invalid(): void
    {
        config()->set('queue.default', 'bogus_driver');

        $response = $this->getJson('/_readiness');

        $response->assertStatus(503);
        $response->assertJson(['status' => 'degraded']);
        $this->assertFalse($response->json('checks.queue.ok'));
        $this->assertSame('bogus_driver', $response->json('checks.queue.driver'));
    }

    public function test_liveness_endpoint_still_works(): void
    {
        $response = $this->get('/up');
        $response->assertOk();
    }
}
