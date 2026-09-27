<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/** The dev stack must keep hot files off the slow Windows bind mount (#288). */
class DockerComposeDevTest extends TestCase
{
    public function test_compiled_views_live_outside_the_bind_mount(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/docker-compose.dev.yml');

        preg_match('/VIEW_COMPILED_PATH=(\S+)/', $config, $match);

        $this->assertNotEmpty($match, 'docker-compose.dev.yml does not set VIEW_COMPILED_PATH.');
        $this->assertStringStartsNotWith('/app', $match[1], 'Compiled views are still on the bind mount.');
    }
}
