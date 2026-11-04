{{-- One headline figure, with its change against the period before when both sides can be quoted. --}}
@php
    use App\Support\PeopleReportColumns as Columns;

    $few = Columns::tooFew($column, $record);
    $unit = \Illuminate\Support\Str::plural($column['unit'] ?? 'item', $few ?? 0);
@endphp
<div>
    <dt class="text-xs font-medium text-slate-500">{{ $label ?? $column['label'] }}</dt>
    @if ($few !== null)
        <dd class="mt-1 text-sm font-medium leading-7 text-slate-500" title="Only {{ $few }} {{ $unit }}, too few to quote">Too few ({{ $few }})</dd>
    @else
        <dd class="tabular mt-1 text-xl font-semibold text-slate-900">{{ Columns::display($column, $record) }}</dd>
    @endif
    @isset($change)
        <dd class="mt-1 min-h-[1.25rem]">
            @include('partials.delta', ['change' => $change, 'unit' => $deltaUnit ?? '', 'versus' => 'before', 'lowerIsBetter' => $lowerIsBetter ?? false])
        </dd>
    @endisset
</div>
