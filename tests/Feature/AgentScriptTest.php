<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentScriptTest extends TestCase
{
    use RefreshDatabase;

    public function test_install_script_is_public_and_has_no_token(): void
    {
        $response = $this->get('/agent/install.sh');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/x-shellscript; charset=UTF-8');
        $body = $response->getContent();

        $this->assertIsString($body);
        $this->assertStringContainsString('#!/bin/bash', $body);
        $this->assertStringContainsString('--token', $body);
        $this->assertStringContainsString('/api/v1/servers/heartbeat', $body);
        $this->assertStringNotContainsString('PINGWISE_TOKEN=pw_srv_', $body);
        $this->assertStringNotContainsString('__PINGWISE_URL__', $body);
    }

    public function test_heartbeat_script_is_public(): void
    {
        $response = $this->get('/agent/heartbeat.sh');

        $response->assertOk();
        $body = $response->getContent();

        $this->assertIsString($body);
        $this->assertStringContainsString('Authorization: Bearer', $body);
        $this->assertStringContainsString('/proc/meminfo', $body);
    }
}
