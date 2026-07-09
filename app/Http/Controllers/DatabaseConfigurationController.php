<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Database configuration section of the Admin Configuration area.
 *
 * Read-only display of the app's ACTIVE database connection (task 04). Switching
 * backends or converting SQLite<->MySQL is an offline CLI/ops operation and a
 * separate future spec — there is no write route here.
 *
 * SECURITY: the connection config array holds secrets (password, and often the
 * username). This controller whitelists a safe subset — driver, database
 * name/path, and host only — so the view can never accidentally dump the whole
 * array or leak the password (invariant 5).
 */
class DatabaseConfigurationController extends Controller
{
    public function edit(): View
    {
        $name = config('database.default');
        $config = config("database.connections.$name", []);

        // Whitelist here, in the controller: only these facts reach the view.
        // NEVER include 'password' (nor 'username'). 'host' is null for sqlite
        // and the view hides the row when it is absent.
        $connection = [
            'driver' => $config['driver'] ?? null,
            'database' => $config['database'] ?? null,
            'host' => $config['host'] ?? null,
        ];

        return view('admin.database.edit', compact('connection'));
    }
}
