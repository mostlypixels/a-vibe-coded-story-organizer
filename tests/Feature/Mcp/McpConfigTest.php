<?php

namespace Tests\Feature\Mcp;

use Tests\TestCase;

class McpConfigTest extends TestCase
{
    public function test_mcp_json_starts_the_server_inside_the_dev_container(): void
    {
        $config = json_decode(file_get_contents(base_path('.mcp.json')), true, flags: JSON_THROW_ON_ERROR);

        $server = $config['mcpServers']['avcso'];

        $this->assertSame(
            'docker compose -f docker-compose.dev.yml exec -T -u laravel app php artisan mcp:start avcso',
            $server['command'].' '.implode(' ', $server['args']),
        );
    }
}
