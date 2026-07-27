<?php

namespace App\View\Components;

use App\Models\Project;
use App\Services\ProjectRevisionsBrowser;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The shared shell for the whole revisions browser: the project's revision
 * sidebar (built once here) beside a content pane. Used by the browser landing
 * page and by the per-field history / compare views, so the sidebar stays put
 * while a reader drills from a field's history into a diff and back.
 *
 * The tree is built in this one place rather than in each controller: the
 * component takes only the Project plus the currently-selected (entity, id,
 * field) — nullable, since the landing page has no selection — and resolves
 * ProjectRevisionsBrowser from the container itself, keeping the three
 * controllers that render it thin.
 */
class RevisionsLayout extends Component
{
    /**
     * The flash key this shell renders its danger alert from, and the one
     * `RevisionController` writes when a revert is refused.
     *
     * Namespaced rather than the generic `error` on purpose. This shell renders
     * whatever sits in the key, so with `error` any *other* feature that flashed
     * it and redirected to a revisions page would have its message appear
     * dressed as a revert conflict. Nothing else writes `error` today; naming
     * the key makes that a property of the code rather than a coincidence
     * somebody has to keep rechecking.
     *
     * It lives here, on the component that renders it, rather than on the
     * controller that writes it — the alert is this shell's, and a Blade
     * template reaching into a controller for a constant would be the wrong way
     * round.
     */
    public const ERROR_KEY = 'revision_error';

    /**
     * The sidebar tree — see ProjectRevisionsBrowser::tree() for its shape.
     *
     * @var Collection<int, object>
     */
    public Collection $tree;

    public function __construct(
        ProjectRevisionsBrowser $browser,
        public Project $project,
        public ?string $entity = null,
        public ?int $id = null,
        public ?string $field = null,
    ) {
        $this->tree = $browser->tree($project);
    }

    /**
     * The refusal message to show, if a revert flashed one — resolved here so
     * the template asks for a value rather than reaching into the session.
     */
    public function errorMessage(): ?string
    {
        return session(self::ERROR_KEY);
    }

    public function render(): View
    {
        return view('components.revisions-layout');
    }
}
