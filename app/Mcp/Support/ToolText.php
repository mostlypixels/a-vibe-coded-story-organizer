<?php

namespace App\Mcp\Support;

use App\Models\Event;
use App\Support\RichText;
use Illuminate\Support\Str;

/** Small text helpers shared by the read tools, so every tool prints a thing the same way. */
final class ToolText
{
    /** One line of plain text from a rich field, for list rows. Empty when the field is empty. */
    public static function short(?string $html, int $limit = 100): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', RichText::toPlainText($html))), $limit);
    }

    /** "Title, 2024-05-01 10:00", with "about" in front of an approximate date. */
    public static function event(Event $event): string
    {
        return "{$event->title}, ".($event->is_approximate ? 'about ' : '').$event->event_datetime->format('Y-m-d H:i');
    }

    /** The rich HTML as stored, so Claude can edit it and send it back. */
    public static function html(?string $html): string
    {
        return trim((string) $html) === '' ? '(empty)' : trim((string) $html);
    }
}
