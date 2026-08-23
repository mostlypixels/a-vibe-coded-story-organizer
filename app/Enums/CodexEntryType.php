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
     * Label for the inception event field ("Born", "Created", "Founded").
     */
    public function inceptionLabel(): string
    {
        return match ($this) {
            self::Character => 'Born',
            self::Location => 'Created',
            self::Organization => 'Founded',
        };
    }

    /**
     * Label for the termination event field ("Died", "Destroyed", "Dissolved").
     */
    public function terminationLabel(): string
    {
        return match ($this) {
            self::Character => 'Died',
            self::Location => 'Destroyed',
            self::Organization => 'Dissolved',
        };
    }

    /**
     * Whether this type has a lifespan (inception/termination, age, existence
     * filter). True for every case today; a future type with no age concept
     * opts out here.
     */
    public function tracksLifespan(): bool
    {
        return match ($this) {
            self::Character, self::Location, self::Organization => true,
        };
    }

    /**
     * The singular label as a lowercase noun, for copy that reads it
     * mid-sentence ("Latest character").
     */
    public function singularNoun(): string
    {
        return strtolower($this->label());
    }

    /**
     * The plural label as a lowercase noun, for copy that reads it mid-sentence
     * ("View all characters"). Deliberately not routeKey(): a URL segment and a
     * display noun happen to match today, but they are not the same thing.
     */
    public function pluralNoun(): string
    {
        return strtolower($this->pluralLabel());
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
