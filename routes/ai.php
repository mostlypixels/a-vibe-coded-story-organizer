<?php

use App\Mcp\Servers\AvcsoServer;
use Laravel\Mcp\Facades\Mcp;

// Stdio only, started by .mcp.json inside Docker. No HTTP route, so no auth surface.
Mcp::local('avcso', AvcsoServer::class);
