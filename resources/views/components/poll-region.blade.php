@props(['id', 'running' => false, 'every' => 4000, 'also' => [], 'doneMessage' => null])
{{--
    A section that keeps itself up to date while a queued job runs, without
    reloading the page: it re-fetches the page in the background and swaps in
    only this section (plus elements listed in `also`, by id) until nothing is
    running (resources/js/poll-region.js).
      <x-poll-region id="kpi-history" :running="$running" :also="['kpi-submit']" done-message="Report ready.">…</x-poll-region>
    The id must be unique on the page and identical on every render.
--}}
<div id="{{ $id }}"
     x-data="pollRegion({ every: {{ (int) $every }}, also: @js(array_values($also)), doneMessage: @js($doneMessage) })"
     data-running="{{ $running ? 'true' : 'false' }}"
     @poll-region-now="refresh()"
     aria-live="polite"
     {{ $attributes }}>
    {{ $slot }}
</div>
