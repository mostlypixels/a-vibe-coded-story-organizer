<?php

namespace App\Enums;

enum CodexEntryType: string
{
    case Character = 'character';
    case Location = 'location';
    case Organization = 'organization';

    /**
     * Human-readable singular label (mirrors SceneStatus::label()).
     */
    public function label(): string
    {
        return match ($this) {
            self::Character => 'Character',
            self::Location => 'Location',
            self::Organization => 'Organization',
        };
    }

    /**
     * Human-readable plural label, used in listings and navigation.
     */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Character => 'Characters',
            self::Location => 'Locations',
            self::Organization => 'Organizations',
        };
    }

    /**
     * The plural, URL-friendly key used in the {type} route segment.
     */
    public function routeKey(): string
    {
        return match ($this) {
            self::Character => 'characters',
            self::Location => 'locations',
            self::Organization => 'organizations',
        };
    }

    /**
     * Resolve a type from its route key (e.g. "characters" => Character).
     */
    public static function fromRouteKey(string $routeKey): self
    {
        return match ($routeKey) {
            'characters' => self::Character,
            'locations' => self::Location,
            'organizations' => self::Organization,
            default => throw new \ValueError("Unknown codex entry type route key [{$routeKey}]."),
        };
    }
}
