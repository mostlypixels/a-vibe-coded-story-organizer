# Export to static files — UI

The only screen is the **Export** tab of Admin → Export & import
(`resources/views/admin/data/index.blade.php`). Today both tabs are "coming soon" text
inside an accessible WAI-ARIA tab shell. We replace the **Export** panel body with a real
form; the **Import** panel stays a stub.

## Export panel form

Inside `#panel-export` (keep the existing tablist / roving-tabindex shell untouched):

```blade
<form method="POST" action="{{ route('admin.data.export') }}" class="mt-6 space-y-6">
    @csrf

    {{-- Project selector — the export is per-project (Q1). Only the current user's
         projects are listable; ownership is re-checked server-side (ExportRequest). --}}
    <div>
        <x-input-label for="project_id" :value="__('Project')" />
        <select id="project_id" name="project_id" required
                class="mt-1 block w-full …">   {{-- match text-input styling --}}
            @foreach ($projects as $project)
                <option value="{{ $project->id }}">{{ $project->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('project_id')" class="mt-2" />
    </div>

    {{-- Include-images toggle. Unchecked → absent → treated as false server-side. --}}
    <label class="flex items-center gap-2">
        <input type="checkbox" name="include_images" value="1" checked
               class="rounded border-gray-300 …" />
        <span class="text-sm text-gray-700">{{ __('Include images') }}</span>
    </label>

    <x-primary-button>{{ __('Export') }}</x-primary-button>
</form>
```

- `$projects` is supplied by `DataTransferController::index` (or `ExportController` if the
  section shell moves): `$request->user()->projects()->orderBy('name')->get()` — **only the
  signed-in user's projects** (a `User hasMany Project` relation; add it if missing — see
  note below).
- Reuse existing components: `x-input-label`, `x-input-error`, `x-primary-button`. There is
  **no `x-select` component yet**; style the native `<select>` to match `x-text-input`
  (`resources/views/components/text-input.blade.php`). Do **not** invent a new component for
  a single use (CLAUDE.md: no abstraction before a second caller).
- Keyboard accessibility is already handled by the surrounding tab shell; the form is native
  controls with labels, so it is accessible by default. Keep semantic HTML.

> [!NOTE]
> `User::projects()` relation: `ProjectController` scopes projects to the user somewhere —
> confirm the relation exists (`$user->projects()`); if the app currently queries
> `Project::where('user_id', …)` inline, either add the `projects()` `HasMany` to `User` or
> mirror the existing query. Pick the one already used on the Dashboard for consistency.

## Empty-state / disabled

- If the user has **no projects**, render a short line ("Create a project first to export
  it.") instead of the form, and link to project creation — mirrors the app's empty-state
  convention (`empty_states` spec, `x-table-empty`). Confirm copy in Q10.

## What the export files look like (reference, not app UI)

The generated HTML is standalone (opened from disk, no app chrome). Keep it simple and
semantic:

- `storyline.html`: `<h1>` project name, then `<h2>`/`<h3>` act & chapter headings, then each
  scene's title + prose (`Str::markdown($scene->contents)`), in `position` order.
- act/chapter `index.html`: `<h1>` name + the rich-HTML `description` rendered verbatim.
- scene `.html`: `<h1>` name, a small metadata line (status label, event title if any), then
  Description (rich HTML), Contents (Markdown→HTML), Notes (rich HTML). Field order/labels →
  Q8.

> [!WARNING]
> Rich-HTML fields (`description`, `notes`) are **already sanitized** at write time
> (`SanitizesRichHtml`), so render them with `{!! … !!}` verbatim ("HTML as is"). Scene
> `contents` is Markdown → `{!! Str::markdown($scene->contents ?? '') !!}` exactly like
> `resources/views/story/index.blade.php:94`. Do not double-escape or re-sanitize.

## CHANGELOG / docs

- Add an `Added` entry under `## [Unreleased]` in `CHANGELOG.md` ("Static-file export of a
  project as a downloadable zip").
- Document the export in `documentation/architecture.md` (a *Static file export* section:
  the service, the tree layout, the image manifest, the admin-area-plus-ProjectPolicy
  authorization exception).
