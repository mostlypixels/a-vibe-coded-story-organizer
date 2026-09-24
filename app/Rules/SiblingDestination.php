<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The value must be the key of one of `$candidates`, and not the key of `$except`.
 *
 * The delete-with-move and move-to-book forms use it. The candidates query holds
 * the "same parent" condition, so a foreign ID fails with the same message as a
 * missing one.
 */
final class SiblingDestination implements ValidationRule
{
    public function __construct(
        private Builder|Relation $candidates,
        private Model $except,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $found = is_scalar($value)
            && (clone $this->candidates)
                ->whereKey($value)
                ->whereKeyNot($this->except->getKey())
                ->exists();

        if (! $found) {
            $fail('validation.exists')->translate();
        }
    }
}
