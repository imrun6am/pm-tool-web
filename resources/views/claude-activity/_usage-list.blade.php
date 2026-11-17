{{--
    A short list on a Claude device page (IP addresses, MAC addresses,
    models): the first $limit rows, then "+N more" with a Show all toggle.
      $items  list of ['value', 'href', 'title', 'events' (?int), 'last_seen' (?Carbon)]
      $noun   singular, for "+3 more IP addresses"
      $empty  text when there are none
      $limit  rows shown before Show all (default 3)
      $bars   true to draw each row's share of the events in the list
--}}
@php
    use Illuminate\Support\Str;

    $limit ??= 3;
    $bars ??= false;
    $hidden = max(0, count($items) - $limit);
    $sum = max(1, (int) collect($items)->sum('events'));
@endphp

@if (empty($items))
    <p class="px-4 py-3 text-sm text-slate-500 sm:px-6">{{ $empty }}</p>
@else
    <div x-data="{ all: false }">
        <ul class="divide-y divide-slate-100">
            @foreach ($items as $i => $item)
                <li class="px-4 py-2.5 sm:px-6" @if ($i >= $limit) x-show="all" x-cloak @endif>
                    <div class="flex items-start justify-between gap-2">
                        <a href="{{ $item['href'] }}" title="{{ $item['title'] }}"
                           class="min-w-0 break-all font-mono text-sm text-slate-800 hover:text-brand-700 hover:underline">{{ $item['value'] }}</a>
                        @if ($i === 0 && count($items) > 1)
                            <span class="badge badge-success flex-none">Latest</span>
                        @endif
                    </div>
                    <p class="mt-0.5 text-xs tabular-nums text-slate-500">
                        @isset($item['events'])
                            {{ number_format($item['events']) }} {{ Str::plural('event', $item['events']) }}
                        @endisset
                        @if ($item['last_seen'])
                            @isset($item['events']) · @endisset
                            last <span title="{{ $item['last_seen']->format('M j, Y H:i') }}">{{ $item['last_seen']->diffForHumans() }}</span>
                        @endif
                    </p>
                    @if ($bars && isset($item['events']))
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                            <div class="h-full rounded-full bg-brand-500" style="width: {{ max(2, round($item['events'] / $sum * 100)) }}%"></div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($hidden > 0)
            <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-2 sm:px-6">
                <span class="text-xs text-slate-500" x-show="!all">+{{ $hidden }} more {{ Str::plural($noun, $hidden) }}</span>
                <span class="text-xs text-slate-500" x-show="all" x-cloak>All {{ count($items) }} {{ Str::plural($noun, count($items)) }}</span>
                <button type="button" class="text-sm font-medium text-brand-700 hover:text-brand-800 hover:underline"
                        @click="all = ! all" :aria-expanded="all.toString()">
                    <span x-show="!all">Show all</span>
                    <span x-show="all" x-cloak>Show fewer</span>
                </button>
            </div>
        @endif
    </div>
@endif
