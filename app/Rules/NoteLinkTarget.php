<?php

namespace App\Rules;

use App\Enums\NoteLinkType;
use App\Models\Project;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

/**
 * The target of a note link: a row of the given type in the note's project.
 * A foreign id fails like a missing id, so a guessed id cannot link across projects.
 *
 * With a type, the value is the id. Without one, the value is `<type>:<id>`, the
 * form of the `link` field on the note create page.
 */
final class NoteLinkTarget implements ValidationRule
{
    public function __construct(
        private Project $project,
        private ?NoteLinkType $type = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $target = $this->type === null
            ? self::resolveKey($this->project, $value)
            : self::resolve($this->project, $this->type, $value);

        if ($target === null) {
            $fail('validation.exists')->translate();
        }
    }

    public static function resolve(Project $project, NoteLinkType $type, mixed $id): ?Model
    {
        if (! is_numeric($id) || (int) $id != $id) {
            return null;
        }

        return $type->queryFor($project)->whereKey((int) $id)->first();
    }

    /** Resolves a `<type>:<id>` key, or returns null. */
    public static function resolveKey(Project $project, mixed $key): ?Model
    {
        if (! is_string($key) || preg_match('/^([a-z]+):(\d+)$/', $key, $matches) !== 1) {
            return null;
        }

        $type = NoteLinkType::tryFrom($matches[1]);

        return $type === null ? null : self::resolve($project, $type, $matches[2]);
    }
}
