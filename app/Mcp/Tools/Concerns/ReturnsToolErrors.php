<?php

namespace App\Mcp\Tools\Concerns;

use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Support\ValidationMessages;

/**
 * Turns a missing model or a failed validation into a tool error.
 *
 * The MCP library turns any thrown exception into a protocol error. Claude then sees an
 * opaque failure, not a message it can act on.
 */
trait ReturnsToolErrors
{
    /** @param  Closure(): (Response|ResponseFactory)  $callback */
    protected function guard(Closure $callback): Response|ResponseFactory
    {
        try {
            return $callback();
        } catch (ModelNotFoundException $exception) {
            $ids = implode(', ', array_map(fn ($id) => '#'.$id, (array) $exception->getIds()));
            $model = str(class_basename((string) $exception->getModel()))->headline()->lower();

            return Response::error($ids === '' ? "No {$model} found." : "No {$model} with ID {$ids} found.");
        } catch (ValidationException $exception) {
            return Response::error(ValidationMessages::from($exception));
        }
    }
}
