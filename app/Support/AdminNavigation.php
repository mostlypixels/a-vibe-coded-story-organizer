<?php

namespace App\Support;

/**
 * View model for the Configuration area's navigation — the sidebar's section
 * list and the Export & import subnav, with the active section resolved from
 * the current route.
 *
 * Mirrors App\Support\ProjectNavigation, for the same reason: the links and
 * their route matchers are reference data, not markup, and keeping them in one
 * class means adding an admin section is a one-line change here instead of an
 * edit spread across a template's inline @php.
 *
 * Each entry is `['label' => string, 'href' => string, 'active' => bool]`,
 * ready for x-sidebar-link. Labels are already translated.
 */
class AdminNavigation
{
    /**
     * The Configuration sidebar, top to bottom.
     *
     * Note "Export & import" matches the whole `admin.data.*` namespace, so it
     * stays highlighted on the subnav's pages below it.
     *
     * @return array<int, array{label: string, href: string, active: bool}>
     */
    public function sections(): array
    {
        return [
            $this->link(__('General settings'), 'admin.settings.edit', 'admin.settings.*'),
            $this->link(__('Appearance & accessibility'), 'admin.appearance.edit', 'admin.appearance.*'),
            $this->link(__('Export & import'), 'admin.data.index', 'admin.data.*'),
            $this->link(__('Database configuration'), 'admin.database.edit', 'admin.database.*'),
            $this->link(__('Revisions'), 'admin.revisions.edit', 'admin.revisions.*'),
        ];
    }

    /**
     * The Export & import subnav. These are ordinary links to distinct URLs —
     * separate controller actions, not JS tabs — so each matches its own route
     * exactly rather than a namespace glob.
     *
     * @return array<int, array{label: string, href: string, active: bool}>
     */
    public function dataSubnav(): array
    {
        return [
            $this->link(__('Export project'), 'admin.data.export-project'),
            $this->link(__('Export ebook'), 'admin.data.export-ebook'),
            $this->link(__('Import'), 'admin.data.import.index'),
        ];
    }

    /**
     * @param  string  $route  Route name the link points at.
     * @param  string|null  $activePattern  routeIs() pattern that marks it
     *                                      active; defaults to $route itself.
     * @return array{label: string, href: string, active: bool}
     */
    private function link(string $label, string $route, ?string $activePattern = null): array
    {
        return [
            'label' => $label,
            'href' => route($route),
            'active' => request()->routeIs($activePattern ?? $route),
        ];
    }
}
