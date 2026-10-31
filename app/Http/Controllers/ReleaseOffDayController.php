<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseOffDayRequest;
use App\Models\Release;
use App\Models\ReleaseOffDay;
use App\Services\ReleaseOffDayService;
use Illuminate\Http\RedirectResponse;

class ReleaseOffDayController extends Controller
{
    public function __construct(private readonly ReleaseOffDayService $offDays) {}

    public function store(ReleaseOffDayRequest $request, Release $release): RedirectResponse
    {
        $this->offDays->add($release, $request->validated());

        return back()->with('success', 'Off-day marked.');
    }

    public function markWeekends(Release $release): RedirectResponse
    {
        $added = $this->offDays->markWeekends($release);

        return back()->with('success', $added
            ? "Marked {$added} weekend day(s) as off."
            : 'All weekends were already marked.');
    }

    public function destroy(Release $release, ReleaseOffDay $offDay): RedirectResponse
    {
        $this->offDays->remove($release, $offDay);

        return back()->with('success', 'Off-day removed.');
    }
}
