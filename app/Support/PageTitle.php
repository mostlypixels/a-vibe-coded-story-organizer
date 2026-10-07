<?php

namespace App\Support;

use App\Models\Book;
use App\Models\Contracts\Revisionable;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Stringable;

/**
 * The document <title> of an authenticated page.
 *
 * Two shapes, one rule: inside a project the title leads with a name
 * ("Melusine - AVCSO"), everywhere else it is the bare app name. The name is
 * the route's book, through {@see Book::displayName()}, when the route has
 * one, else the route's project. `displayName()` falls back to the project's
 * own name for an unnamed book, so a project's sole book renders exactly the
 * title it did before the book layer existed. The browser tab is the only
 * place a writer juggling several projects can tell two windows apart, so
 * the leading name goes first — tabs truncate from the right.
 *
 * The page part goes in front of the name, for the same reason: two tabs of
 * one book must differ ("Le guet-apens - Edit - Marius - AVCSO"). A show or
 * edit page names its entity. Other project pages use the current breadcrumb.
 * A page with no trail can give its own part through `<x-app-layout title>`.
 *
 * The app name itself comes from config (APP_NAME), never a literal.
 */
class PageTitle implements Stringable
{
    /** Separator between the title parts. */
    private const SEPARATOR = ' - ';

    /**
     * @param  list<string>  $pageParts  Most specific first.
     */
    public function __construct(
        private readonly ?Project $project,
        private readonly ?Book $book = null,
        private readonly array $pageParts = [],
    ) {}

    public static function forRequest(ProjectNavigation $navigation, Breadcrumbs $breadcrumbs, Request $request): self
    {
        $pageParts = $navigation->routeProject === null
            ? []
            : self::pageParts($navigation, $breadcrumbs, $request);

        return new self($navigation->routeProject, $navigation->routeBook, $pageParts);
    }

    /** A view's own page part replaces the derived one. */
    public function withPage(?string $page): self
    {
        return $page === null || $page === ''
            ? $this
            : new self($this->project, $this->book, [$page]);
    }

    public function __toString(): string
    {
        $name = $this->book?->displayName() ?? $this->project?->name;

        return implode(self::SEPARATOR, [
            ...$this->pageParts,
            ...($name === null ? [] : [$name]),
            (string) config('app.name'),
        ]);
    }

    /**
     * @return list<string>
     */
    private static function pageParts(ProjectNavigation $navigation, Breadcrumbs $breadcrumbs, Request $request): array
    {
        $routeName = (string) $request->route()?->getName();
        $isEdit = Str::endsWith($routeName, '.edit');
        $entity = self::boundEntity($request);

        if ($entity !== null && ($isEdit || Str::endsWith($routeName, '.show'))) {
            // The project or book name is in the title already.
            $isNamed = $entity->is($navigation->routeProject) || $entity->is($navigation->routeBook);
            $parts = $isNamed ? [] : [$entity->revisionDisplayName()];

            return $isEdit ? [...$parts, __('Edit')] : $parts;
        }

        // The Dashboard is the project's own page. The name says it all.
        if ($navigation->homeActive) {
            return [];
        }

        $current = $breadcrumbs->current();

        return $current === null ? [] : [$current->label];
    }

    /** The last route-bound model with a display name: the page's own entity. */
    private static function boundEntity(Request $request): (Model&Revisionable)|null
    {
        $entity = null;

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model && $parameter instanceof Revisionable) {
                $entity = $parameter;
            }
        }

        return $entity;
    }
}
