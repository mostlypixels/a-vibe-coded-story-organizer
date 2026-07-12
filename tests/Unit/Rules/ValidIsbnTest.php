<?php

namespace Tests\Unit\Rules;

use App\Rules\ValidIsbn;
use Tests\TestCase;

/**
 * Unit tests for the ISBN-13 validation rule used by the project metadata form.
 * The rule strips punctuation only to check the checksum; it never normalizes
 * the stored value, and it is a no-op on an empty value (pairs with `nullable`).
 */
class ValidIsbnTest extends TestCase
{
    /**
     * Run the rule and capture whether it failed and the message it reported.
     *
     * @return array{bool, ?string}
     */
    private function validate(mixed $value): array
    {
        $failed = false;
        $message = null;

        (new ValidIsbn)->validate('isbn', $value, function (string $reason) use (&$failed, &$message) {
            $failed = true;
            $message = $reason;
        });

        return [$failed, $message];
    }

    public function test_a_valid_isbn13_without_hyphens_passes(): void
    {
        [$failed] = $this->validate('9780306406157');

        $this->assertFalse($failed);
    }

    public function test_a_valid_isbn13_with_hyphens_passes(): void
    {
        [$failed] = $this->validate('978-0-306-40615-7');

        $this->assertFalse($failed);
    }

    public function test_a_valid_isbn13_with_spaces_passes(): void
    {
        [$failed] = $this->validate('978 0 306 40615 7');

        $this->assertFalse($failed);
    }

    public function test_an_empty_value_does_not_run(): void
    {
        [$failedNull] = $this->validate(null);
        [$failedEmpty] = $this->validate('');

        $this->assertFalse($failedNull);
        $this->assertFalse($failedEmpty);
    }

    public function test_a_wrong_length_fails(): void
    {
        [$failed, $message] = $this->validate('978030640615');

        $this->assertTrue($failed);
        $this->assertStringContainsString('13-digit', $message);
    }

    public function test_non_numeric_characters_fail(): void
    {
        [$failed] = $this->validate('97803064061X7');

        $this->assertTrue($failed);
    }

    public function test_a_bad_checksum_digit_fails(): void
    {
        // Same as the valid ISBN but with the check digit changed from 7 to 8.
        [$failed, $message] = $this->validate('9780306406158');

        $this->assertTrue($failed);
        $this->assertStringContainsString('checksum', $message);
    }
}
