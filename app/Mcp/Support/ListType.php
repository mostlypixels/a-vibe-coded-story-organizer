<?php

namespace App\Mcp\Support;

/** The kinds of row the `list` tool can page through. */
enum ListType: string
{
    case Plotline = 'plotline';
    case Event = 'event';
    case Note = 'note';
    case NoteCategory = 'note-category';
    case Character = 'character';
    case Location = 'location';
    case Organization = 'organization';
}
