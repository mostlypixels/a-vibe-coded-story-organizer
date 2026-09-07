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
     * from. The previous URL comes from the session, not user input, so
     * there is no open-redirect check to write.
     */
    public function update(UpdatePageSizeRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->to($this->previousUrlWithoutPage());
    }

    /** Strips `page` so the writer lands on page 1 of the same sorted, filtered list. */
    private function previousUrlWithoutPage(): string
    {
        $previous = url()->previous();
        $parts = parse_url($previous);

        parse_str($parts['query'] ?? '', $query);
        unset($query['page']);

        $url = ($parts['path'] ?? '/');

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        if (isset($parts['fragment'])) {
            $url .= '#'.$parts['fragment'];
        }

        return $url;
    }
}
