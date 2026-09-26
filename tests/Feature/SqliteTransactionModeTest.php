<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Two quick autosaves returned 500 "database is locked". The database cache
 * increments the rate limiter in a DEFERRED transaction, and SQLite does not wait
 * when a read lock must become a write lock. Tests cannot run two requests at
 * once, so this test guards the setting.
 */
class SqliteTransactionModeTest extends TestCase
{
    public function test_sqlite_transactions_take_the_write_lock_first(): void
    {
        $this->assertSame('IMMEDIATE', config('database.connections.sqlite.transaction_mode'));
    }
}
