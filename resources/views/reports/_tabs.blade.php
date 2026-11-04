{{-- Moves between the reports this person may see. --}}
@if (auth()->user()->isLead())
    <nav class="flex gap-1 border-b border-slate-200" aria-label="Reports">
        @foreach (['reports.flow' => 'Flow', 'reports.time' => 'Time'] + (auth()->user()->isAdmin() ? ['reports.people' => 'People'] : []) as $routeName => $name)
            <a href="{{ route($routeName) }}" @if (request()->routeIs($routeName.'*')) aria-current="page" @endif
               @class(['-mb-px border-b-2 px-3 py-2 text-sm font-medium',
                       'border-brand-600 text-brand-700' => request()->routeIs($routeName.'*'),
                       'border-transparent text-slate-500 hover:text-slate-800' => ! request()->routeIs($routeName.'*')])>{{ $name }}</a>
        @endforeach
    </nav>
@endif
