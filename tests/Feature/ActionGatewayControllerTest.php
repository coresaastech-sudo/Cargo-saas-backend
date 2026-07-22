<?php

namespace Tests\Feature;

use Tests\TestCase;

class ActionGatewayControllerTest extends TestCase
{
    public function test_action_is_required(): void
    {
        $response = $this->postJson('/api/v1/back/action');

        $response
            ->assertStatus(422)
            ->assertJsonPath('response_code', 'POSTING_CODE_REQUIRED');
    }

    public function test_unknown_action_returns_not_found(): void
    {
        $response = $this->withHeader('posting_code', 'unknown.action')->postJson('/api/v1/back/action');

        $response
            ->assertStatus(404)
            ->assertJsonPath('response_code', 'ACTION_NOT_FOUND');
    }
}
