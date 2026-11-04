<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ReadsMineFilter
{
    /**
     * Whether to list only the viewer's own items. `?mine=1` and `?mine=0` say
     * so outright. Otherwise Developers and QA get their own, unless the visit
     * already narrows the list, so a link to a release still shows all of it.
     */
    protected function mineFilter(Request $request, bool $narrowed): bool
    {
        return $request->has('mine')
            ? $request->boolean('mine')
            : $request->user()->hasLimitedAccess() && ! $narrowed;
    }
}
