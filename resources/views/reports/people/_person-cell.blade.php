<div class="min-w-0">
    <a href="{{ $href }}" class="block truncate text-sm font-medium text-slate-800 hover:text-brand-700">{{ $row['user']->name }}</a>
    <span class="block truncate text-xs text-slate-500">
        {{ \App\Models\User::ROLES[$row['user']->role] ?? $row['user']->role }}@if ($row['teams'] !== []), {{ implode(', ', $row['teams']) }}@endif
    </span>
    @if ($row['tag'] || $row['user']->statusTag())
        <span class="mt-0.5 inline-flex items-center rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600">{{ $row['tag'] ?? $row['user']->statusTag() }}</span>
    @endif
</div>
