<?php

namespace App\Support;

/**
 * Session keys that the app layout shows on the next page.
 *
 * A controller sets the message. No page needs its own status check.
 */
final class Flash
{
    public const SUCCESS = 'success';
}
