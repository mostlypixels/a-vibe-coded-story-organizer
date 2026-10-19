<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateCodexEntryTool;
use App\Mcp\Tools\CreateNoteTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTool;
use App\Mcp\Tools\ProjectOutlineTool;
use App\Mcp\Tools\SaveSceneReviewTool;
use App\Mcp\Tools\SceneContextTool;
use App\Mcp\Tools\SearchTool;
use App\Mcp\Tools\SetCodexAttributeTool;
use App\Mcp\Tools\ShowTool;
use App\Mcp\Tools\UpdateCodexEntryTool;
use App\Mcp\Tools\UpdateNoteTool;
use App\Mcp\Tools\UpdateSceneTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('avcso')]
#[Version('1.0.0')]
#[Instructions(<<<'TEXT'
    Read and annotate the author's projects: books, scenes, notes and codex.
    Never write the author's prose: no tool changes scene contents or book front and back matter.
    IDs show as #<id>. Rich fields take HTML.
    If the server is down, the Docker stack is stopped: ask the author to run `make up`, then reconnect with `/mcp`.
    TEXT)]
class AvcsoServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ListProjectsTool::class,
        ProjectOutlineTool::class,
        ListTool::class,
        ShowTool::class,
        SearchTool::class,
        SceneContextTool::class,
        UpdateSceneTool::class,
        SaveSceneReviewTool::class,
        CreateNoteTool::class,
        UpdateNoteTool::class,
        CreateCodexEntryTool::class,
        UpdateCodexEntryTool::class,
        SetCodexAttributeTool::class,
    ];
}
