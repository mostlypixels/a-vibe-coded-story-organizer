<?php

namespace App\Support;

/**
 * A count with its noun, e.g. "1 scene" or "3 scenes".
 *
 * The delete-with-move dialogs build this text both for one entity and for each row
 * of a list, so both must agree.
 */
class CountPhrase
{
    public static function make(int $count, string $singular, string $plural): string
    {
        // A count of 0 matches no range, and the plural text then keeps its leading space.
        return trim(trans_choice('{1} :count '.$singular.'|[2,*] :count '.$plural, $count, ['count' => $count]));
    }
}
