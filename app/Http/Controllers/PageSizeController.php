<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePageSizeRequest;
use Illuminate\Http\RedirectResponse;

/**
 * The whole-user row-count preference every entity list paginates by.
 *
 * No policy and no `ProjectPolicy` walk: the preference has no owning
 * project, and the write always targets the acting user.
 */
class PageSizeController extends Controller
{
    /**
     * Persist the picked size, then return to the list the request came
     * from. The previous URL can come from the Referer header, so it is
     * checked before use.
     */
    public function update(UpdatePageSizeRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->to($this->previousUrlWithoutPage());
    }

    /** Strips `page` so the writer lands on page 1 of the same sorted, filtered list. */
    private function previousUrlWithoutPage(): string
    {
        $parts = parse_url(url()->previous()) ?: [];
        $path = $parts['path'] ?? '/';

        // A browser reads `//host` or `/\host` as a different site. Send these, and URLs of a different host, to home.
        if (($parts['host'] ?? null) !== request()->getHost()
            || ! str_starts_with($path, '/')
            || in_array(substr($path, 1, 1), ['/', '\\'], true)) {
            return '/';
        }

        parse_str($parts['query'] ?? '', $query);
        unset($query['page']);

        $url = $path;

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        if (isset($parts['fragment'])) {
            $url .= '#'.$parts['fragment'];
        }

        return $url;
    }
}
