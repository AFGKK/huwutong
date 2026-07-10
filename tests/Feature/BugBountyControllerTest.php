<?php

namespace Tests\Feature;

use App\Models\BugBountyReport;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class BugBountyControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function public_can_submit_report()
    {
        $data = [
            'title' => 'Security vulnerability found',
            'description' => 'Detailed description of the issue.',
            'reporter_email' => 'test@example.com',
            'vulnerability_type' => 'XSS',
        ];

        $response = $this->postJson('/bug-bounty/reports', $data);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Thank you for your submission. We will review it within 48 hours.');
    }

    /** @test */
    public function public_can_get_policy()
    {
        $response = $this->getJson('/bug-bounty/policy');

        $response->assertOk()
            ->assertJsonStructure(['program_name', 'scope', 'rules', 'contact']);
    }

    /** @test */
    public function security_txt_is_accessible()
    {
        $response = $this->get('/.well-known/security.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $response->assertSee('Contact: mailto:security@huwutong.com');
    }

    /** @test */
    public function security_policy_page_is_accessible()
    {
        $response = $this->get('/security-policy');

        $response->assertOk();
        $response->assertSee('Bug Bounty Program');
    }

    /** @test */
    public function hall_of_fame_page_is_accessible()
    {
        $response = $this->get('/hall-of-fame');

        $response->assertOk();
        $response->assertSee('Hall of Fame');
    }
}
