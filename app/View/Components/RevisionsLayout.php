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

    public function render(): View
    {
        return view('components.revisions-layout');
    }
}
