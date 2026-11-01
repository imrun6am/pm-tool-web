@props(['id'])
{{-- Region that updates in place: every non-GET form inside it submits in
     the background and only this region is replaced, cut from the page the
     action redirects back to (resources/js/app.js). Links and GET filters
     inside still navigate the whole page. Wrap everything the actions can
     change; the id must be unique on the page. --}}
<turbo-frame id="{{ $id }}" target="_top" data-live-region {{ $attributes->merge(['class' => 'live-region']) }}>
    {{ $slot }}
</turbo-frame>
