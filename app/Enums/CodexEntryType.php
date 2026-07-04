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
     * Every type's route key, for route constraints and nav iteration.
     *
     * @return array<int, string>
     */
    public static function routeKeys(): array
    {
        return array_map(fn (self $type) => $type->routeKey(), self::cases());
    }

    /**
     * Resolve a type from its route key (e.g. "characters" => Character).
     */
    public static function fromRouteKey(string $routeKey): self
    {
        foreach (self::cases() as $type) {
            if ($type->routeKey() === $routeKey) {
                return $type;
            }
        }

        throw new \ValueError("Unknown codex entry type route key [{$routeKey}].");
    }
}
