<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/** The Docker nginx config must not answer routes that Laravel owns. */
class NginxConfigTest extends TestCase
{
    public function test_robots_txt_reaches_laravel(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/docker/nginx.conf');

        preg_match('/location = \/robots\.txt\s*\{([^}]*)\}/', $config, $match);

        $this->assertNotEmpty($match, 'nginx.conf has no robots.txt location.');
        $this->assertStringContainsString('/index.php', $match[1]);
    }
}
