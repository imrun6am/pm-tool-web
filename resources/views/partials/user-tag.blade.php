{{-- Status tag next to a user's name on surviving data. Usage: @include('partials.user-tag', ['tagUser' => $someUser]) --}}
@if (($tag = $tagUser?->statusTag()) !== null)
    <span class="ml-1 inline-flex items-center rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ $tag }}</span>
@endif
