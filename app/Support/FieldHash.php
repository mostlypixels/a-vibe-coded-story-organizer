<?php

namespace App\Support;

/**
 * The conflict hash of one autosaved field value.
 *
 * The edit page, the autosave endpoint and the reverter compare these hashes.
 *
 * > [!WARNING]
 * > Every caller must use this method. If one side hashes a different string,
 * > every save of that field returns a conflict.
 */
final class FieldHash
{
    public static function of(mixed $value): string
    {
        return hash('sha256', (string) ($value ?? ''));
    }
}
