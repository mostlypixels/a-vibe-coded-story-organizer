<?php

namespace App\Mcp\Support;

/** The kinds of entity the `show` tool can print. */
enum ShowType: string
{
    case Project = 'project';
    case Book = 'book';
    case Act = 'act';
    case Chapter = 'chapter';
    case Scene = 'scene';
    case Plotline = 'plotline';
    case Event = 'event';
    case Note = 'note';
    case Codex = 'codex';
}
