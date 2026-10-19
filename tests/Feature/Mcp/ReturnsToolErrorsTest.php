<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Tools\Concerns\ReturnsToolErrors;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Tests\TestCase;

class ReturnsToolErrorsTest extends TestCase
{
    use RefreshDatabase;

    private function run_guarded(callable $callback): Response|ResponseFactory
    {
        $probe = new class
        {
            use ReturnsToolErrors;

            public function run(callable $callback): Response|ResponseFactory
            {
                return $this->guard($callback(...));
            }
        };

        return $probe->run($callback);
    }

    public function test_an_unknown_id_becomes_a_tool_error(): void
    {
        $response = $this->run_guarded(fn () => Project::query()->findOrFail(999));

        $this->assertTrue($response->isError());
        $this->assertSame('No project with ID #999 found.', (string) $response->content());
    }

    public function test_a_validation_failure_becomes_a_tool_error_with_its_messages(): void
    {
        $response = $this->run_guarded(
            fn () => Validator::make(['name' => ''], ['name' => 'required'])->validate()
        );

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('name', (string) $response->content());
    }

    public function test_a_successful_response_passes_through(): void
    {
        $response = $this->run_guarded(fn () => Response::text('fine'));

        $this->assertFalse($response->isError());
    }
}
