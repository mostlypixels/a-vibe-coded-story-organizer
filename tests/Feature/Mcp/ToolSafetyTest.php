<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\AvcsoServer;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use ReflectionClass;
use Tests\TestCase;

class ToolSafetyTest extends TestCase
{
    /** Fields the author owns. No tool may take them as input. */
    private const FORBIDDEN_ARGUMENTS = [
        'contents', 'dedication', 'acknowledgements', 'preface', 'postface', 'is_main', 'position',
    ];

    /** @return list<Tool> */
    private function tools(): array
    {
        $classes = (new ReflectionClass(AvcsoServer::class))->getDefaultProperties()['tools'];

        return array_map(fn (string $class): Tool => app($class), $classes);
    }

    public function test_every_server_tool_is_allowed_in_the_claude_settings(): void
    {
        $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true, flags: JSON_THROW_ON_ERROR);
        $allowed = $settings['permissions']['allow'];

        foreach ($this->tools() as $tool) {
            $this->assertContains('mcp__avcso__'.$tool->name(), $allowed);
        }
    }

    public function test_the_allow_list_has_no_wildcard_for_the_server(): void
    {
        $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($settings['permissions']['allow'] as $rule) {
            if (str_starts_with($rule, 'mcp__avcso')) {
                $this->assertStringNotContainsString('*', $rule);
                $this->assertNotSame('mcp__avcso', $rule);
            }
        }
    }

    public function test_no_tool_is_destructive(): void
    {
        foreach ($this->tools() as $tool) {
            $attributes = (new ReflectionClass($tool))->getAttributes(IsDestructive::class);

            $this->assertSame([], $attributes, $tool->name().' must not be destructive.');
        }
    }

    public function test_no_tool_takes_an_author_owned_field(): void
    {
        foreach ($this->tools() as $tool) {
            $properties = (array) ($tool->toArray()['inputSchema']['properties'] ?? []);

            $this->assertSame(
                [],
                array_intersect(self::FORBIDDEN_ARGUMENTS, array_keys($properties)),
                $tool->name().' takes a field the author owns.',
            );
        }
    }
}
