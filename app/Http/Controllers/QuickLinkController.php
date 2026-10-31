<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickLinkRequest;
use App\Models\QuickLink;
use App\Services\QuickLinkService;
use Illuminate\Http\RedirectResponse;

class QuickLinkController extends Controller
{
    public function __construct(private readonly QuickLinkService $quickLinks) {}

    public function store(QuickLinkRequest $request): RedirectResponse
    {
        $this->quickLinks->create($request->validated(), $request->user());

        return back()->with('success', 'Link added.')->with('quick-links-open', true);
    }

    public function update(QuickLinkRequest $request, QuickLink $quickLink): RedirectResponse
    {
        $this->authorize('update', $quickLink);

        $this->quickLinks->update($quickLink, $request->validated());

        return back()->with('success', 'Link updated.')->with('quick-links-open', true);
    }

    /** Pinning needs only visibility — you may pin a link you cannot edit. */
    public function pin(QuickLink $quickLink): RedirectResponse
    {
        $this->authorize('pin', $quickLink);

        $pinned = $this->quickLinks->togglePin($quickLink, request()->user());

        return back()
            ->with('success', $pinned ? 'Link pinned.' : 'Link unpinned.')
            ->with('quick-links-open', true);
    }

    public function destroy(QuickLink $quickLink): RedirectResponse
    {
        $this->authorize('delete', $quickLink);

        $quickLink->delete();

        return back()->with('success', 'Link deleted.')->with('quick-links-open', true);
    }
}
