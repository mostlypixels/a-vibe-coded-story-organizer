<?php

namespace App\Enums;

/**
 * How a card on the Write page shows. The values are stored in `editor_cards.state`:
 * never change them. A `Closed` card keeps its row, so a linked note stays hidden.
 */
enum EditorCardState: string
{
    case Expanded = 'expanded';
    case Collapsed = 'collapsed';
    case Closed = 'closed';
}
